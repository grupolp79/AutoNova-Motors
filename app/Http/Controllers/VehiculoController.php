<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VehiculoController extends Controller implements HasMiddleware
{
    /**
     * Permisos por rol. El catálogo (index) y el detalle (show) son públicos (RF-02).
     * Alta y modificación: Empleado y Admin (RF-01). Baja: solo Admin (RF-10).
     */
    public static function middleware(): array
    {
        return [
            new Middleware('rol:empleado,admin', only: ['create', 'store', 'edit', 'update']),
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
        [$datos, $urls] = $this->validar($request);

        // Transacción: si falla al guardar las fotos, no queda el vehículo a medias
        $vehiculo = DB::transaction(function () use ($datos, $urls) {
            $vehiculo = Vehiculo::create($datos);
            $this->guardarFotos($vehiculo, $urls);
            return $vehiculo;
        });

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

    public function update(Request $request, Vehiculo $vehiculo)
    {
        [$datos, $urls] = $this->validar($request);

        DB::transaction(function () use ($vehiculo, $datos, $urls) {
            $vehiculo->update($datos);
            // Las fotos se reemplazan: se borran las anteriores y se cargan las del textarea
            $vehiculo->fotos()->delete();
            $this->guardarFotos($vehiculo, $urls);
        });

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
            // Las fotos y los favoritos se borran solos por el cascadeOnDelete de la clave foránea
            $vehiculo->delete();
        } catch (QueryException $e) {
            // Red de seguridad: si justo apareció un registro relacionado entre el conteo y el DELETE,
            // la base lo rechaza. SQLSTATE 23000 = violación de integridad (MariaDB 1451, SQLite 19).
            if (($e->errorInfo[0] ?? null) !== '23000') {
                throw $e; // otro error de base: no se oculta
            }

            return redirect()->route('vehiculos.show', $vehiculo)
                ->with('error', 'No se puede eliminar este vehículo porque otros registros dependen de él.');
        }

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo eliminado correctamente.');
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
     * Valida los datos del formulario. Devuelve los datos del vehículo y la lista de URLs de fotos.
     */
    private function validar(Request $request): array
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
            'fotos' => 'nullable|string',
            // Cada línea tiene que ser una URL http/https (la columna 'ruta' admite hasta 255 caracteres)
            'lista_fotos' => 'array|max:255',
            'lista_fotos.*' => 'url:http,https|max:255',
        ], [
            'lista_fotos.*.url' => 'La línea :position de fotos no es una URL válida.',
            'lista_fotos.*.max' => 'La línea :position de fotos es demasiado larga (máx. 255 caracteres).',
            'lista_fotos.max' => 'Se permiten como máximo 255 fotos.',
        ]);

        // Solo el Admin puede marcar destacados (RF-12). Para el Empleado el campo ni se muestra:
        // al crear queda en falso y al editar se conserva el valor que ya tenía.
        // El checkbox no se envía si está desmarcado, por eso se usa boolean().
        if ($request->user()?->esAdmin()) {
            $datos['destacado'] = $request->boolean('destacado');
        }
        unset($datos['fotos'], $datos['lista_fotos']);

        return [$datos, $urls];
    }

    /**
     * Crea una foto por cada URL; el orden es el de las líneas del textarea.
     */
    private function guardarFotos(Vehiculo $vehiculo, array $urls): void
    {
        foreach ($urls as $i => $url) {
            $vehiculo->fotos()->create(['ruta' => $url, 'orden' => $i]);
        }
    }
}
