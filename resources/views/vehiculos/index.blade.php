@extends('layouts.app')

@section('titulo', 'Catálogo')

@push('estilos')
    {{-- noUiSlider: barras de rango con dos bolitas --}}
    <link href="https://cdn.jsdelivr.net/npm/nouislider@15.8.1/dist/nouislider.min.css" rel="stylesheet">
@endpush

@section('contenido')
    @php
        // Grupos de checkboxes: nombre del parámetro => [título, opciones valor => texto]
        $grupos = [
            'estado' => ['Estado de venta', \App\Models\Vehiculo::ESTADOS],
            'marca' => ['Marca', $opciones['marcas']->combine($opciones['marcas'])->all()],
            'transmision' => ['Transmisión', \App\Models\Vehiculo::TRANSMISIONES],
            'combustible' => ['Combustible', \App\Models\Vehiculo::COMBUSTIBLES],
        ];

        // Vista de gestión: Empleado y Admin ven la misma página con herramientas extra.
        // Es solo lo que se muestra: los permisos se controlan en el servidor (middleware del controlador).
        $usuario = auth()->user();
        $gestion = $usuario?->puedeGestionar() ?? false;
        $esAdmin = $usuario?->esAdmin() ?? false;

        // Totales de TODA la tabla (no dependen de los filtros); solo hacen falta en la vista de gestión
        $totales = $gestion ? \App\Models\Vehiculo::totalesPorEstado() : null;
        $plurales = ['disponible' => 'Disponibles', 'reservado' => 'Reservados', 'vendido' => 'Vendidos'];
    @endphp

    {{-- ===== Barra de gestión (solo Empleado y Admin; para el resto no existe en el HTML) ===== --}}
    @if ($gestion)
        <section class="barra-gestion" aria-label="Herramientas de gestión">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="etiqueta-gestion">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                    Vista de gestión · {{ $esAdmin ? 'Admin' : 'Empleado' }}
                </span>
                <div class="d-flex flex-wrap gap-2">
                    @if ($esAdmin)
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-people" aria-hidden="true"></i> Usuarios
                        </a>
                        <a href="{{ route('reportes.ventas') }}" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-bar-chart" aria-hidden="true"></i> Reporte de ventas
                        </a>
                    @endif
                    <a href="{{ route('vehiculos.create') }}" class="btn btn-acento btn-sm">+ Nuevo vehículo</a>
                </div>
            </div>

            <div class="totales-gestion" id="totalesGestion">
                <div class="total-item">
                    <span class="total-punto" aria-hidden="true"></span>
                    <div>
                        <div class="total-numero" data-total="total">{{ $totales['total'] }}</div>
                        <div class="total-etiqueta">Vehículos en total</div>
                    </div>
                </div>
                @foreach ($plurales as $estado => $texto)
                    <div class="total-item color-{{ $estado }}">
                        <span class="total-punto" aria-hidden="true"></span>
                        <div>
                            <div class="total-numero" data-total="{{ $estado }}">{{ $totales[$estado] }}</div>
                            <div class="total-etiqueta">{{ $texto }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Un solo formulario GET: al aplicar, los filtros quedan en la URL (?marca[]=Ford&km_max=...) --}}
    <form method="GET" action="{{ route('vehiculos.index') }}" id="formFiltros">

        {{-- ===== Cabecera con buscador (compacta en la vista de gestión) ===== --}}
        <section class="hero-catalogo {{ $gestion ? 'hero-compacto' : '' }}">
            @if ($gestion)
                <h1>Catálogo de vehículos</h1>
            @else
                <h1>Encontrá tu próximo auto</h1>
                <p class="hero-frase">Autos 0 km y usados, con fotos, precio y todos los datos a la vista.</p>
            @endif

            <div class="buscador">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="q" maxlength="100"
                       placeholder="Buscar por marca, modelo o año..." value="{{ $filtros['q'] ?? '' }}" aria-label="Buscar">
                <button type="submit" class="btn btn-acento">Buscar</button>
            </div>
        </section>

        <div class="row g-4">

            {{-- ===== Panel de filtros ===== --}}
            <div class="col-lg-3">
                {{-- En celular el panel está oculto y se abre con este botón (collapse de Bootstrap) --}}
                <button class="btn btn-outline-dark w-100 d-lg-none mb-3" type="button"
                        data-bs-toggle="collapse" data-bs-target="#panelFiltros" aria-controls="panelFiltros">
                    <i class="bi bi-funnel" aria-hidden="true"></i> Filtros
                </button>

                {{-- d-lg-block: en pantallas grandes se muestra siempre --}}
                <div class="collapse d-lg-block" id="panelFiltros">
                    <div class="card panel-filtros">

                        <div class="pb-3">
                            <label for="orden" class="filtro-titulo">Ordenar por</label>
                            <select id="orden" name="orden" class="form-select form-select-sm">
                                @foreach (\App\Models\Vehiculo::ORDENES as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(($filtros['orden'] ?? '') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Precio: el slider escribe los valores en los inputs ocultos, que son los que se envían --}}
                        <div class="filtro-grupo">
                            <span class="filtro-titulo">Precio</span>
                            <div class="small fw-semibold mb-3" id="precioTexto"></div>
                            <div id="sliderPrecio" class="mx-2 mb-2"></div>
                            <input type="hidden" name="precio_min" id="precio_min" value="{{ $filtros['precio_min'] ?? '' }}">
                            <input type="hidden" name="precio_max" id="precio_max" value="{{ $filtros['precio_max'] ?? '' }}">
                        </div>

                        <div class="filtro-grupo">
                            <span class="filtro-titulo">Kilometraje</span>
                            <div class="small fw-semibold mb-3" id="kmTexto"></div>
                            <div id="sliderKm" class="mx-2 mb-2"></div>
                            <input type="hidden" name="km_min" id="km_min" value="{{ $filtros['km_min'] ?? '' }}">
                            <input type="hidden" name="km_max" id="km_max" value="{{ $filtros['km_max'] ?? '' }}">
                        </div>

                        <div class="filtro-grupo">
                            <span class="filtro-titulo">Condición</span>
                            @foreach (['' => 'Todos', '0km' => '0 km', 'usado' => 'Usado'] as $valor => $texto)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="condicion" value="{{ $valor }}"
                                           id="condicion_{{ $valor ?: 'todos' }}" @checked(($filtros['condicion'] ?? '') === $valor)>
                                    <label class="form-check-label" for="condicion_{{ $valor ?: 'todos' }}">{{ $texto }}</label>
                                </div>
                            @endforeach
                        </div>

                        <div class="filtro-grupo">
                            <span class="filtro-titulo">Año</span>
                            <div class="row g-2">
                                {{-- Los años vienen de la base; (string) para comparar con el valor del GET --}}
                                @foreach (['anio_desde' => 'Desde', 'anio_hasta' => 'Hasta'] as $nombre => $texto)
                                    <div class="col-6">
                                        <select name="{{ $nombre }}" class="form-select form-select-sm" aria-label="Año {{ $texto }}">
                                            <option value="">{{ $texto }}</option>
                                            @foreach ($opciones['anios'] as $anio)
                                                <option value="{{ $anio }}" @selected(($filtros[$nombre] ?? '') === (string) $anio)>{{ $anio }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Checkboxes con name="grupo[]": PHP los recibe como array (selección múltiple) --}}
                        @foreach ($grupos as $nombre => [$titulo, $valores])
                            <div class="filtro-grupo">
                                <span class="filtro-titulo">{{ $titulo }}</span>
                                @foreach ($valores as $valor => $texto)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="{{ $nombre }}[]" value="{{ $valor }}"
                                               id="{{ $nombre }}_{{ $loop->index }}" @checked(in_array($valor, $filtros[$nombre] ?? []))>
                                        <label class="form-check-label" for="{{ $nombre }}_{{ $loop->index }}">{{ $texto }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        <div class="filtro-grupo">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="destacados" value="1" id="destacados"
                                       @checked(isset($filtros['destacados']))>
                                <label class="form-check-label fw-semibold" for="destacados">Solo destacados</label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 pt-1">
                            <button type="submit" class="btn btn-dark">Aplicar</button>
                            {{-- Limpiar = volver al catálogo sin parámetros --}}
                            <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-dark">Limpiar filtros</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Resultados ===== --}}
            <div class="col-lg-9">
                {{-- total() es la cantidad de resultados de todas las páginas, no solo de esta --}}
                <p class="text-muted mb-3">
                    {{ $vehiculos->total() }} {{ $vehiculos->total() === 1 ? 'vehículo encontrado' : 'vehículos encontrados' }}
                </p>

                @if ($vehiculos->isEmpty())
                    <div class="card estado-vacio">
                        <i class="bi bi-car-front" aria-hidden="true"></i>
                        <h2 class="h5 fw-semibold mt-3">No encontramos vehículos con esos filtros.</h2>
                        <p class="text-muted">Probá sacando alguno o buscá con otras palabras.</p>
                        <div><a href="{{ route('vehiculos.index') }}" class="btn btn-dark">Limpiar filtros</a></div>
                    </div>
                @endif

                <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4">
                    @foreach ($vehiculos as $vehiculo)
                        @php
                            // Portada = primera foto; la relación ya viene ordenada por 'orden'
                            $portada = $vehiculo->fotos->first();
                            $detalle = route('vehiculos.show', $vehiculo);
                        @endphp
                        <div class="col">
                            {{-- En la vista de gestión la tarjeta es más compacta y lleva una franja con el color del estado --}}
                            <article class="card h-100 tarjeta-vehiculo {{ $gestion ? 'tarjeta-gestion color-' . $vehiculo->estado : '' }}">
                                {{-- La foto también lleva al detalle; tabindex -1 para no repetir el foco del botón "Ver detalle" --}}
                                <a href="{{ $detalle }}" class="tarjeta-foto d-block" tabindex="-1">
                                    @if ($portada)
                                        <img src="{{ $portada->ruta }}" loading="lazy"
                                             alt="{{ $vehiculo->marca }} {{ $vehiculo->modelo }}">
                                    @else
                                        <div class="sin-foto">
                                            <i class="bi bi-camera" aria-hidden="true"></i>
                                            <span class="small">Sin foto</span>
                                        </div>
                                    @endif
                                    {{-- Estado y destacado sobre la foto --}}
                                    <div class="tarjeta-etiquetas">@include('vehiculos._badges')</div>
                                </a>

                                <div class="card-body">
                                    <h2 class="tarjeta-titulo">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</h2>
                                    {{-- resumen: la descripción o, si está vacía, un texto armado con los datos (ver el modelo) --}}
                                    <p class="tarjeta-descripcion">{{ \Illuminate\Support\Str::limit($vehiculo->resumen, 140) }}</p>

                                    <ul class="datos-fila">
                                        <li><i class="bi bi-calendar3" aria-hidden="true"></i>{{ $vehiculo->anio }}</li>
                                        <li><i class="bi bi-speedometer2" aria-hidden="true"></i>{{ $vehiculo->kilometraje == 0 ? '0 km' : number_format($vehiculo->kilometraje, 0, ',', '.') . ' km' }}</li>
                                        <li><i class="bi bi-gear" aria-hidden="true"></i>{{ \App\Models\Vehiculo::TRANSMISIONES[$vehiculo->transmision] ?? $vehiculo->transmision }}</li>
                                        <li>
                                            <i class="bi {{ $vehiculo->combustible === 'electrico' ? 'bi-lightning-charge' : 'bi-fuel-pump' }}" aria-hidden="true"></i>{{ \App\Models\Vehiculo::COMBUSTIBLES[$vehiculo->combustible] ?? $vehiculo->combustible }}
                                        </li>
                                    </ul>

                                    <p class="precio">U$S {{ number_format($vehiculo->precio, 0, ',', '.') }}</p>

                                    @if ($gestion)
                                        <p class="tarjeta-meta">
                                            Alta {{ $vehiculo->created_at?->format('d/m/Y') }} · Modificado {{ $vehiculo->updated_at?->format('d/m/Y') }}
                                        </p>
                                    @endif

                                    <div class="tarjeta-pie">
                                        <a href="{{ $detalle }}" class="btn btn-dark {{ $gestion ? 'btn-sm flex-grow-1' : 'w-100' }}">Ver detalle</a>

                                        {{-- Editar: Empleado y Admin. Eliminar: solo Admin (su formulario está al final de la página) --}}
                                        @if ($gestion)
                                            <a href="{{ route('vehiculos.edit', $vehiculo) }}" class="btn btn-outline-dark btn-sm">
                                                <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                                            </a>
                                        @endif
                                        @if ($esAdmin)
                                            <button type="submit" form="eliminar-{{ $vehiculo->id }}" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash" aria-hidden="true"></i> Eliminar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                {{-- Paginación Bootstrap 5; withQueryString() hace que los links mantengan los filtros --}}
                <div class="mt-4">
                    {{ $vehiculos->links() }}
                </div>
            </div>
        </div>
    </form>

    {{--
        Formularios de "Eliminar" (solo Admin). Van fuera del formulario de filtros porque HTML no permite
        un formulario dentro de otro; cada botón de la tarjeta los envía con el atributo form="eliminar-ID".
        Los formularios no soportan DELETE: @method lo simula y @csrf protege el envío.
    --}}
    @if ($esAdmin)
        @foreach ($vehiculos as $vehiculo)
            <form id="eliminar-{{ $vehiculo->id }}" action="{{ route('vehiculos.destroy', $vehiculo) }}" method="POST" class="d-none"
                  onsubmit="return confirm('¿Seguro que querés eliminar este vehículo? Esta acción no se puede deshacer.');">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/nouislider@15.8.1/dist/nouislider.min.js"></script>
    <script>
        // Formato de números uruguayo: 15000 -> "15.000"
        const formatear = n => Math.round(n).toLocaleString('es-UY');

        /**
         * Arma una barra con dos bolitas.
         * - limites: [mínimo, máximo] reales de la base (vienen del controlador)
         * - Si había un filtro aplicado, las bolitas arrancan en esos valores; si no, en los límites.
         * - Cuando una bolita queda en el límite, el input oculto queda vacío y ese extremo no filtra.
         */
        function crearSlider(idSlider, idMin, idMax, idTexto, limites, paso, mostrar) {
            const inputMin = document.getElementById(idMin);
            const inputMax = document.getElementById(idMax);
            const texto = document.getElementById(idTexto);
            const slider = document.getElementById(idSlider);

            noUiSlider.create(slider, {
                start: [inputMin.value || limites[0], inputMax.value || limites[1]],
                connect: true,   // pinta la barra entre las dos bolitas
                step: paso,
                range: { min: limites[0], max: limites[1] },
            });

            // 'update' se dispara al crear el slider y cada vez que se mueve una bolita
            slider.noUiSlider.on('update', function (valores) {
                const min = Number(valores[0]);
                const max = Number(valores[1]);
                texto.textContent = mostrar(min) + ' – ' + mostrar(max);
                inputMin.value = min > limites[0] ? min : '';
                inputMax.value = max < limites[1] ? max : '';
            });
        }

        crearSlider('sliderPrecio', 'precio_min', 'precio_max', 'precioTexto',
            @json($opciones['precio']), 500, n => 'U$S ' + formatear(n));

        crearSlider('sliderKm', 'km_min', 'km_max', 'kmTexto',
            @json($opciones['km']), 1000, n => formatear(n) + ' km');

        // Al enviar, se desactivan los campos vacíos para que no ensucien la URL (?q=&precio_min=...)
        document.getElementById('formFiltros').addEventListener('submit', function () {
            this.querySelectorAll('input, select').forEach(function (campo) {
                if (campo.name && campo.value === '') campo.disabled = true;
            });
        });

        // Si se vuelve con el botón "Atrás", el navegador puede mostrar la página con esos campos
        // todavía desactivados; acá se reactivan
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('#formFiltros [disabled]').forEach(campo => campo.disabled = false);
        });
    </script>
@endpush
