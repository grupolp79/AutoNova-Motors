<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Vehiculo;
use App\Models\VehiculoFoto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class VehiculoController extends Controller implements HasMiddleware
{
    // Regla y mensaje de la descripción: los mismos en el formulario completo y en la edición rápida de la ficha
    private const REGLA_DESCRIPCION = 'nullable|string|max:' . Vehiculo::DESCRIPCION_MAX;
    private const MENSAJE_DESCRIPCION_LARGA = 'La descripción puede tener hasta :max caracteres. Acortala un poco y volvé a guardar.';

    /**
     * Permisos por rol. El catálogo (index) y el detalle (show) son públicos (RF-02).
     * Alta y modificación (también la edición rápida de la descripción): Empleado y Admin (RF-01). Baja: solo Admin (RF-10).
     */
    public static function middleware(): array
    {
        return [
            new Middleware('rol:empleado,admin', only: ['create', 'store', 'edit', 'update', 'actualizarDescripcion']),
            new Middleware('rol:admin', only: ['destroy']),
        ];
    }

    /**
     * Catálogo con búsqueda y filtros (por GET), 12 por página.
     * with('fotos') hace eager loading para no hacer una consulta por cada tarjeta (problema N+1).
     */
    public function index(Request $request)
    {
        $filtros = $this->filtrosValidos($request);

        $vehiculos = Vehiculo::with('fotos')
            ->filtrar($filtros)                 // scopeFiltrar del modelo
            ->ordenar($filtros['orden'] ?? '')  // scopeOrdenar del modelo
            ->paginate(12)
            ->withQueryString();                // los links de páginas conservan los filtros

        return view('vehiculos.index', [
            'vehiculos' => $vehiculos,
            'filtros' => $filtros,
            'opciones' => $this->opcionesFiltros(),
        ]);
    }

    public function create()
    {
        // Se pasa un vehículo vacío para que el formulario compartido funcione igual que en editar
        $vehiculo = new Vehiculo(['estado' => 'disponible']);

        return view('vehiculos.create', compact('vehiculo'));
    }

    public function store(Request $request)
    {
        [$datos, $urls, $archivos] = $this->validar($request);

        // Archivos que se escriben en el disco: si algo falla, se borran (la base se deshace sola con la transacción)
        $escritos = [];

        try {
            // Transacción: si falla al guardar las fotos, no queda el vehículo a medias.
            // El id existe recién después de create(), por eso los archivos se guardan después.
            $vehiculo = DB::transaction(function () use ($datos, $urls, $archivos, &$escritos) {
                $vehiculo = Vehiculo::create($datos);
                $orden = $this->guardarUrls($vehiculo, $urls, 0);
                $this->guardarArchivos($vehiculo, $archivos, $orden, $escritos);

                // Auditoría dentro de la misma transacción: si algo falla, no queda ni el vehículo ni el registro
                Auditoria::registrar('vehiculo.crear',
                    "Alta de {$vehiculo->marca} {$vehiculo->modelo} {$vehiculo->anio}: " . $this->textoCampo('precio', $vehiculo->precio)
                    . ', estado ' . $this->textoCampo('estado', $vehiculo->estado) . '.',
                    $vehiculo);

                return $vehiculo;
            });
        } catch (Throwable $e) {
            $this->borrarArchivos($escritos);
            throw $e;
        }

        return redirect()->route('vehiculos.show', $vehiculo)
            ->with('success', 'Vehículo creado correctamente.');
    }

    public function show(Vehiculo $vehiculo)
    {
        // Route model binding: Laravel busca el vehículo por id (404 si no existe)
        $vehiculo->load('fotos');

        return view('vehiculos.show', compact('vehiculo'));
    }

    public function edit(Vehiculo $vehiculo)
    {
        $vehiculo->load('fotos');

        return view('vehiculos.edit', compact('vehiculo'));
    }

    /**
     * Guardar la edición. Las fotos NO se reemplazan todas: se conservan las que ya tenía (con su orden),
     * se quitan las marcadas con "Quitar" (y las URLs que se borraron del textarea),
     * y las nuevas (URLs y archivos) van al final.
     */
    public function update(Request $request, Vehiculo $vehiculo)
    {
        $vehiculo->load('fotos');
        [$datos, $urls, $archivos, $aQuitar, $urlsNuevas] = $this->validar($request, $vehiculo);

        $escritos = [];

        try {
            DB::transaction(function () use ($vehiculo, $datos, $archivos, $aQuitar, $urlsNuevas, &$escritos) {
                // fill() carga los datos nuevos sin guardar: así se puede ver qué cambió antes de save()
                $vehiculo->fill($datos);
                $cambios = $this->describirCambios($vehiculo);
                $vehiculo->save();

                // Fotos: se quitan las elegidas y las nuevas van después de la última que queda
                VehiculoFoto::whereIn('id', $aQuitar->pluck('id'))->delete();
                $orden = (int) $vehiculo->fotos()->max('orden') + 1;
                $orden = $this->guardarUrls($vehiculo, $urlsNuevas, $orden);
                $this->guardarArchivos($vehiculo, $archivos, $orden, $escritos);

                // En la auditoría van solo las cantidades de fotos, nunca nombres de archivo
                $agregadas = count($urlsNuevas) + count($archivos);
                $partes = array_filter([
                    $cambios,
                    $agregadas || $aQuitar->isNotEmpty() ? $this->textoFotos($agregadas, $aQuitar->count()) : '',
                ]);

                Auditoria::registrar('vehiculo.editar',
                    "{$vehiculo->marca} {$vehiculo->modelo} {$vehiculo->anio}. " . ($partes ? implode(' ', $partes) : 'Sin cambios.'),
                    $vehiculo);
            });
        } catch (Throwable $e) {
            // Falló: se borran los archivos nuevos; los de las fotos quitadas siguen en su lugar
            $this->borrarArchivos($escritos);
            throw $e;
        }

        // Recién con la base ya guardada se borran del disco los archivos de las fotos quitadas
        // (una URL externa solo se quita de la base: no hay archivo propio)
        $this->borrarArchivos($aQuitar->map->rutaEnDisco()->filter()->all());

        return redirect()->route('vehiculos.show', $vehiculo)
            ->with('success', 'Vehículo actualizado correctamente.');
    }

    public function destroy(Vehiculo $vehiculo)
    {
        // Leads, turnos y reseñas usan restrictOnDelete: no se puede borrar un vehículo con historial.
        // Se cuentan antes de intentar borrar para explicarle al usuario el motivo real.
        $leads = $vehiculo->leads()->count();
        $turnos = $vehiculo->turnos()->count();
        $resenas = $vehiculo->resenas()->count();

        if ($leads + $turnos + $resenas > 0) {
            return redirect()->route('vehiculos.show', $vehiculo)
                ->with('error', $this->mensajeNoSePuedeEliminar($vehiculo, $leads, $turnos, $resenas));
        }

        try {
            // Baja y auditoría en una transacción: si la base rechaza el DELETE, tampoco queda el registro
            DB::transaction(function () use ($vehiculo) {
                // Las fotos y los favoritos se borran solos por el cascadeOnDelete de la clave foránea
                $vehiculo->delete();

                // El objeto conserva sus datos (y su id) después del delete, así queda escrito qué se borró
                Auditoria::registrar('vehiculo.eliminar',
                    "Baja de {$vehiculo->marca} {$vehiculo->modelo} {$vehiculo->anio} (" . $this->textoCampo('precio', $vehiculo->precio)
                    . ', estado ' . $this->textoCampo('estado', $vehiculo->estado) . ').',
                    $vehiculo);
            });
        } catch (QueryException $e) {
            // Red de seguridad: si justo apareció un registro relacionado entre el conteo y el DELETE,
            // la base lo rechaza. SQLSTATE 23000 = violación de integridad (MariaDB 1451, SQLite 19).
            if (($e->errorInfo[0] ?? null) !== '23000') {
                throw $e; // otro error de base: no se oculta
            }

            return redirect()->route('vehiculos.show', $vehiculo)
                ->with('error', 'No se puede eliminar este vehículo porque otros registros dependen de él.');
        }

        // Ya eliminado en la base: se borra del disco la carpeta con sus fotos subidas (si tenía)
        Storage::disk('vehiculos')->deleteDirectory((string) $vehiculo->id);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo eliminado correctamente.');
    }

    /**
     * Edición rápida de la descripción desde la ficha del vehículo (panel "Descripción").
     * Enviarla vacía borra la descripción. Los errores van en su propia "bolsa" ('descripcion')
     * para mostrarlos dentro del panel y no mezclarlos con otros formularios de la ficha.
     */
    public function actualizarDescripcion(Request $request, Vehiculo $vehiculo)
    {
        $datos = $request->validateWithBag('descripcion', [
            'descripcion' => self::REGLA_DESCRIPCION,
        ], [
            'descripcion.max' => self::MENSAJE_DESCRIPCION_LARGA,
        ]);

        // Solo espacios o vacío = sin descripción
        $texto = filled($datos['descripcion'] ?? null) ? trim($datos['descripcion']) : null;

        DB::transaction(function () use ($vehiculo, $texto) {
            $vehiculo->update(['descripcion' => $texto]);

            // En la auditoría va solo qué vehículo y si se cambió o se borró: NUNCA el texto
            Auditoria::registrar('vehiculo.descripcion',
                "{$vehiculo->marca} {$vehiculo->modelo} {$vehiculo->anio}: " . ($texto === null ? 'descripción borrada.' : 'descripción actualizada.'),
                $vehiculo);
        });

        return redirect()->route('vehiculos.show', $vehiculo)->with('success', 'Descripción actualizada');
    }

    /**
     * Texto de los cambios para la auditoría, comparando lo cargado con fill() contra lo que había en la base.
     * Ej.: "Precio: U$S 15.000 → U$S 14.000. Estado: Disponible → Reservado."
     * La descripción solo se marca como cambiada (puede ser larga). Devuelve '' si no cambió nada.
     */
    private function describirCambios(Vehiculo $vehiculo): string
    {
        $nombres = [
            'marca' => 'Marca', 'modelo' => 'Modelo', 'anio' => 'Año', 'precio' => 'Precio', 'kilometraje' => 'Kilometraje',
            'transmision' => 'Transmisión', 'combustible' => 'Combustible', 'estado' => 'Estado',
            'destacado' => 'Destacado', 'descripcion' => 'Descripción',
        ];

        $partes = [];
        foreach ($nombres as $campo => $nombre) {
            $antes = $vehiculo->getOriginal($campo);
            $despues = $vehiculo->getAttribute($campo);

            // Los números se comparan como números: 15000.00 y 15000 son el mismo precio
            $igual = is_numeric($antes) && is_numeric($despues) ? (float) $antes == (float) $despues : $antes == $despues;
            if ($igual) {
                continue;
            }

            $partes[] = $campo === 'descripcion'
                ? 'Descripción modificada.'
                : "{$nombre}: " . $this->textoCampo($campo, $antes) . ' → ' . $this->textoCampo($campo, $despues) . '.';
        }

        return implode(' ', $partes);
    }

    /** Cómo se muestra un valor en el texto de la auditoría (precio con U$S, estado con su nombre, etc.). */
    private function textoCampo(string $campo, $valor): string
    {
        return match ($campo) {
            'precio' => 'U$S ' . number_format((float) $valor, 0, ',', '.'),
            'kilometraje' => number_format((float) $valor, 0, ',', '.') . ' km',
            'estado' => Vehiculo::ESTADOS[$valor] ?? (string) $valor,
            'transmision' => Vehiculo::TRANSMISIONES[$valor] ?? (string) $valor,
            'combustible' => Vehiculo::COMBUSTIBLES[$valor] ?? (string) $valor,
            'destacado' => $valor ? 'Sí' : 'No',
            default => (string) $valor,
        };
    }

    /**
     * Arma el aviso cuando no se puede eliminar, diciendo qué registros lo impiden
     * y qué hacer según el estado actual del vehículo.
     */
    private function mensajeNoSePuedeEliminar(Vehiculo $vehiculo, int $leads, int $turnos, int $resenas): string
    {
        $partes = array_filter([
            $leads ? $leads . ($leads === 1 ? ' consulta (lead)' : ' consultas (leads)') : null,
            $turnos ? $turnos . ($turnos === 1 ? ' turno' : ' turnos') : null,
            $resenas ? $resenas . ($resenas === 1 ? ' reseña' : ' reseñas') : null,
        ]);

        // "A", "A y B" o "A, B y C"
        $ultima = array_pop($partes);
        $detalle = $partes ? implode(', ', $partes) . ' y ' . $ultima : $ultima;

        $mensaje = "No se puede eliminar {$vehiculo->marca} {$vehiculo->modelo} porque tiene registros asociados: {$detalle}. "
            . 'Se conservan como historial de la automotora.';

        return $mensaje . ($vehiculo->estado === 'vendido'
            ? ' Como ya figura como "Vendido", queda en el sistema como registro de la venta.'
            : ' Si ya no está a la venta, cambiá su estado a "Vendido" desde Editar.');
    }

    /**
     * Valida los parámetros GET del catálogo. A diferencia del formulario de alta,
     * acá no se muestran errores: valid() devuelve solo los parámetros que pasaron
     * la validación y los inválidos se ignoran (como si no hubieran venido).
     */
    private function filtrosValidos(Request $request): array
    {
        $reglas = [
            'q' => 'nullable|string|max:100',
            'precio_min' => 'nullable|numeric|min:0',
            'precio_max' => 'nullable|numeric|min:0',
            'km_min' => 'nullable|integer|min:0',
            'km_max' => 'nullable|integer|min:0',
            'condicion' => 'nullable|in:0km,usado',
            'anio_desde' => 'nullable|integer|min:1900|max:2100',
            'anio_hasta' => 'nullable|integer|min:1900|max:2100',
            // Si un valor del grupo es inválido, se ignora el grupo entero
            'estado' => 'array',
            'estado.*' => 'in:' . implode(',', array_keys(Vehiculo::ESTADOS)),
            'marca' => 'array',
            'marca.*' => 'string|max:60',
            'transmision' => 'array',
            'transmision.*' => 'in:' . implode(',', array_keys(Vehiculo::TRANSMISIONES)),
            'combustible' => 'array',
            'combustible.*' => 'in:' . implode(',', array_keys(Vehiculo::COMBUSTIBLES)),
            'destacados' => 'nullable|in:1',
            'orden' => 'nullable|in:' . implode(',', array_filter(array_keys(Vehiculo::ORDENES))),
        ];

        $validos = Validator::make($request->query(), $reglas)->valid();

        // Solo se quedan los parámetros conocidos y que tienen valor
        return array_filter(
            Arr::only($validos, array_keys($reglas)),
            fn ($valor) => $valor !== null && $valor !== ''
        );
    }

    /**
     * Opciones para armar el panel de filtros, sacadas de la base:
     * marcas y años que existen, y límites reales de precio y kilometraje.
     */
    private function opcionesFiltros(): array
    {
        // Los límites se redondean al paso de la barra (500 y 1.000) para que las bolitas caigan justo
        $precioMin = floor((Vehiculo::min('precio') ?? 0) / 500) * 500;
        $precioMax = ceil((Vehiculo::max('precio') ?? 0) / 500) * 500;
        $kmMin = floor((Vehiculo::min('kilometraje') ?? 0) / 1000) * 1000;
        $kmMax = ceil((Vehiculo::max('kilometraje') ?? 0) / 1000) * 1000;

        return [
            'marcas' => Vehiculo::distinct()->orderBy('marca')->pluck('marca'),
            'anios' => Vehiculo::distinct()->orderByDesc('anio')->pluck('anio'),
            // noUiSlider falla si mínimo y máximo son iguales (ej. base vacía), por eso se separan un paso
            'precio' => [$precioMin, max($precioMax, $precioMin + 500)],
            'km' => [$kmMin, max($kmMax, $kmMin + 1000)],
        ];
    }

    /**
     * Valida los datos del formulario (alta y edición).
     * Devuelve [datos del vehículo, URLs del textarea, archivos subidos, fotos a quitar, URLs nuevas].
     * Las dos últimas solo tienen sentido al editar ($vehiculo con sus fotos ya cargadas).
     */
    private function validar(Request $request, ?Vehiculo $vehiculo = null): array
    {
        // Se separa el textarea en líneas, se sacan espacios y líneas vacías
        $urls = collect(preg_split('/\r\n|\r|\n/', (string) $request->input('fotos')))
            ->map(fn ($linea) => trim($linea))
            ->filter()
            ->values()
            ->all();

        // Se agrega la lista al request para validarla junto con el resto de los campos
        $request->merge(['lista_fotos' => $urls]);

        $datos = $request->validate([
            'marca' => 'required|string|max:60',
            'modelo' => 'required|string|max:60',
            'anio' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'precio' => 'required|numeric|min:0|max:99999999.99',
            'kilometraje' => 'required|integer|min:0',
            // Solo se aceptan los valores de los enum (las claves de las constantes del modelo)
            'transmision' => 'required|in:' . implode(',', array_keys(Vehiculo::TRANSMISIONES)),
            'combustible' => 'required|in:' . implode(',', array_keys(Vehiculo::COMBUSTIBLES)),
            'estado' => 'required|in:' . implode(',', array_keys(Vehiculo::ESTADOS)),
            // Mini descripción opcional; si viene vacía se guarda null (middleware ConvertEmptyStringsToNull)
            'descripcion' => self::REGLA_DESCRIPCION,
            'fotos' => 'nullable|string',
            // Cada línea tiene que ser una URL http/https (la columna 'ruta' admite hasta 255 caracteres)
            'lista_fotos' => 'array',
            'lista_fotos.*' => 'url:http,https|max:255',
            // Fotos subidas como archivo: solo imágenes JPG, PNG o WebP de hasta 5 MB.
            // 'image' y 'mimes' miran el CONTENIDO del archivo, no el nombre; SVG no se acepta porque puede llevar scripts.
            'archivos' => 'nullable|array',
            'archivos.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:' . Vehiculo::FOTO_MAX_KB,
            // Casillas "Quitar" de la edición: ids de fotos
            'quitar' => 'nullable|array',
            'quitar.*' => 'integer',
        ], [
            'lista_fotos.*.url' => 'La línea :position de fotos no es una URL válida.',
            'lista_fotos.*.max' => 'La línea :position de fotos es demasiado larga (máx. 255 caracteres).',
            'descripcion.max' => self::MENSAJE_DESCRIPCION_LARGA,
            // 'uploaded': el archivo no llegó bien, por ejemplo porque supera upload_max_filesize de PHP
            'archivos.*.uploaded' => 'La foto :position no se pudo subir: puede pesar más de lo que acepta el servidor. Probá con una de hasta 5 MB.',
            'archivos.*.file' => 'La foto :position no se pudo subir. Probá elegirla de nuevo.',
            'archivos.*.image' => 'La foto :position no es una imagen JPG, PNG o WebP.',
            'archivos.*.mimes' => 'La foto :position no es una imagen JPG, PNG o WebP.',
            'archivos.*.max' => 'La foto :position pesa más de 5 MB. Achicala o elegí otra.',
            'archivos.array' => 'No se pudieron leer las fotos elegidas. Probá de nuevo.',
        ]);

        $archivos = $request->file('archivos', []);

        // Al editar: qué fotos se quitan y qué URLs son nuevas
        [$aQuitar, $urlsNuevas] = $vehiculo ? $this->planDeFotos($vehiculo, $urls, $request->input('quitar', [])) : [collect(), $urls];

        // Tope de fotos por vehículo, contando las que quedan, las URLs nuevas y los archivos
        $quedan = $vehiculo ? $vehiculo->fotos->count() - $aQuitar->count() : 0;
        if ($quedan + count($urlsNuevas) + count($archivos) > Vehiculo::MAX_FOTOS) {
            throw ValidationException::withMessages([
                'fotos' => 'Cada vehículo puede tener hasta ' . Vehiculo::MAX_FOTOS . ' fotos entre URLs y archivos. Quitá alguna y volvé a guardar.',
            ]);
        }

        // Solo el Admin puede marcar destacados (RF-12). Para el Empleado el campo ni se muestra:
        // al crear queda en falso y al editar se conserva el valor que ya tenía.
        // El checkbox no se envía si está desmarcado, por eso se usa boolean().
        if ($request->user()?->esAdmin()) {
            $datos['destacado'] = $request->boolean('destacado');
        }
        unset($datos['fotos'], $datos['lista_fotos'], $datos['archivos'], $datos['quitar']);

        return [$datos, $urls, $archivos, $aQuitar, $urlsNuevas];
    }

    /**
     * Qué pasa con las fotos al editar:
     *   - se quitan las marcadas con "Quitar" (solo se aceptan ids de fotos de ESTE vehículo)
     *     y las URLs externas que se borraron del textarea (el textarea muestra solo las externas);
     *   - son nuevas las URLs del textarea que el vehículo todavía no tenía.
     * Las fotos subidas no están en el textarea, así que editar sin tocar nada no las pierde.
     * Devuelve [colección de VehiculoFoto a quitar, array de URLs nuevas].
     */
    private function planDeFotos(Vehiculo $vehiculo, array $urls, array $quitarIds): array
    {
        $quitarIds = array_map('intval', $quitarIds);

        $aQuitar = $vehiculo->fotos->filter(fn (VehiculoFoto $foto) => in_array($foto->id, $quitarIds, true)
            || (! $foto->esSubida() && ! in_array($foto->ruta, $urls, true)));

        // URLs que ya tiene (y quedan) o que se quitaron a propósito: no se vuelven a agregar
        $conocidas = $vehiculo->fotos->reject->esSubida()->pluck('ruta')->all();

        $urlsNuevas = collect($urls)->unique()->reject(fn ($url) => in_array($url, $conocidas, true))->values()->all();

        return [$aQuitar->values(), $urlsNuevas];
    }

    /**
     * Crea una foto por cada URL; el orden es el de las líneas del textarea.
     */
    /** Guarda las URLs externas desde el orden indicado. Devuelve el orden que sigue. */
    private function guardarUrls(Vehiculo $vehiculo, array $urls, int $orden): int
    {
        foreach ($urls as $url) {
            $vehiculo->fotos()->create(['ruta' => $url, 'orden' => $orden++]);
        }

        return $orden;
    }

    /**
     * Guarda los archivos subidos en el disco 'vehiculos', carpeta {id del vehículo}.
     * store() usa hashName(): un nombre aleatorio con la extensión del tipo REAL del archivo
     * (detectado por su contenido), nunca el nombre que mandó el usuario.
     * $escritos junta las rutas escritas para poder borrarlas si después algo falla.
     */
    private function guardarArchivos(Vehiculo $vehiculo, array $archivos, int $orden, array &$escritos): void
    {
        foreach ($archivos as $archivo) {
            $ruta = $archivo->store((string) $vehiculo->id, 'vehiculos'); // "5/Xy7...40 letras.jpg"
            $escritos[] = $ruta;

            $vehiculo->fotos()->create(['ruta' => VehiculoFoto::PREFIJO_SUBIDAS . $ruta, 'orden' => $orden++]);
        }
    }

    /** Borra archivos del disco 'vehiculos' (rutas dentro del disco). */
    private function borrarArchivos(array $rutas): void
    {
        if ($rutas) {
            Storage::disk('vehiculos')->delete($rutas);
        }
    }

    /** "Fotos: 2 agregadas, 1 quitada." para la auditoría. */
    private function textoFotos(int $agregadas, int $quitadas): string
    {
        return 'Fotos: ' . $agregadas . ($agregadas === 1 ? ' agregada' : ' agregadas')
            . ', ' . $quitadas . ($quitadas === 1 ? ' quitada' : ' quitadas') . '.';
    }
}
