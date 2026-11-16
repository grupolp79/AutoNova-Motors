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
    @endphp

    <h1 class="mb-4">Catálogo de vehículos</h1>

    {{-- Un solo formulario GET: al aplicar, los filtros quedan en la URL (?marca[]=Ford&km_max=...) --}}
    <form method="GET" action="{{ route('vehiculos.index') }}" id="formFiltros">
        <div class="row g-4">

            {{-- ===== Panel de filtros ===== --}}
            <div class="col-lg-3">
                {{-- En celular el panel está oculto y se abre con este botón (collapse de Bootstrap) --}}
                <button class="btn btn-outline-dark w-100 d-lg-none mb-3" type="button"
                        data-bs-toggle="collapse" data-bs-target="#panelFiltros">
                    Filtros
                </button>

                {{-- d-lg-block: en pantallas grandes se muestra siempre --}}
                <div class="collapse d-lg-block" id="panelFiltros">
                    <div class="card card-body">

                        <div class="mb-3">
                            <label for="orden" class="form-label fw-semibold">Ordenar por</label>
                            <select id="orden" name="orden" class="form-select form-select-sm">
                                @foreach (\App\Models\Vehiculo::ORDENES as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(($filtros['orden'] ?? '') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Precio: el slider escribe los valores en los inputs ocultos, que son los que se envían --}}
                        <div class="mb-4">
                            <div class="fw-semibold mb-1">Precio</div>
                            <div class="small text-muted mb-3" id="precioTexto"></div>
                            <div id="sliderPrecio" class="mx-2"></div>
                            <input type="hidden" name="precio_min" id="precio_min" value="{{ $filtros['precio_min'] ?? '' }}">
                            <input type="hidden" name="precio_max" id="precio_max" value="{{ $filtros['precio_max'] ?? '' }}">
                        </div>

                        <div class="mb-4">
                            <div class="fw-semibold mb-1">Kilometraje</div>
                            <div class="small text-muted mb-3" id="kmTexto"></div>
                            <div id="sliderKm" class="mx-2"></div>
                            <input type="hidden" name="km_min" id="km_min" value="{{ $filtros['km_min'] ?? '' }}">
                            <input type="hidden" name="km_max" id="km_max" value="{{ $filtros['km_max'] ?? '' }}">
                        </div>

                        <div class="mb-3">
                            <div class="fw-semibold mb-1">Condición</div>
                            @foreach (['' => 'Todos', '0km' => '0 km', 'usado' => 'Usado'] as $valor => $texto)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="condicion" value="{{ $valor }}"
                                           id="condicion_{{ $valor ?: 'todos' }}" @checked(($filtros['condicion'] ?? '') === $valor)>
                                    <label class="form-check-label" for="condicion_{{ $valor ?: 'todos' }}">{{ $texto }}</label>
                                </div>
                            @endforeach
                        </div>

                        <div class="mb-3">
                            <div class="fw-semibold mb-1">Año</div>
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
                            <div class="mb-3">
                                <div class="fw-semibold mb-1">{{ $titulo }}</div>
                                @foreach ($valores as $valor => $texto)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="{{ $nombre }}[]" value="{{ $valor }}"
                                               id="{{ $nombre }}_{{ $loop->index }}" @checked(in_array($valor, $filtros[$nombre] ?? []))>
                                        <label class="form-check-label" for="{{ $nombre }}_{{ $loop->index }}">{{ $texto }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="destacados" value="1" id="destacados"
                                   @checked(isset($filtros['destacados']))>
                            <label class="form-check-label fw-semibold" for="destacados">Solo destacados</label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-dark">Aplicar</button>
                            {{-- Limpiar = volver al catálogo sin parámetros --}}
                            <a href="{{ route('vehiculos.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Búsqueda y resultados ===== --}}
            <div class="col-lg-9">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white">
                        {{-- Ícono de lupa en SVG inline --}}
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                    </span>
                    <input type="search" name="q" class="form-control" maxlength="100"
                           placeholder="Buscar por marca, modelo o año..." value="{{ $filtros['q'] ?? '' }}" aria-label="Buscar">
                    <button type="submit" class="btn btn-dark">Buscar</button>
                </div>

                {{-- total() es la cantidad de resultados de todas las páginas, no solo de esta --}}
                <p class="text-muted">
                    {{ $vehiculos->total() }} {{ $vehiculos->total() === 1 ? 'vehículo encontrado' : 'vehículos encontrados' }}
                </p>

                @if ($vehiculos->isEmpty())
                    <div class="alert alert-info">
                        No se encontraron vehículos con esos filtros.
                        <a href="{{ route('vehiculos.index') }}" class="alert-link">Limpiar filtros</a>
                    </div>
                @endif

                <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4">
                    @foreach ($vehiculos as $vehiculo)
                        @php
                            // Portada = primera foto; la relación ya viene ordenada por 'orden'
                            $portada = $vehiculo->fotos->first();
                        @endphp
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                @if ($portada)
                                    <img src="{{ $portada->ruta }}" class="card-img-top object-fit-cover" style="height: 180px"
                                         alt="{{ $vehiculo->marca }} {{ $vehiculo->modelo }}">
                                @else
                                    <div class="bg-secondary-subtle d-flex align-items-center justify-content-center text-muted"
                                         style="height: 180px">Sin foto</div>
                                @endif

                                <div class="card-body">
                                    <div class="mb-2">@include('vehiculos._badges')</div>
                                    <h5 class="card-title mb-1">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</h5>
                                    <p class="card-text text-muted small mb-2">
                                        {{ $vehiculo->anio }} ·
                                        {{ $vehiculo->kilometraje == 0 ? '0 km' : number_format($vehiculo->kilometraje, 0, ',', '.') . ' km' }}
                                    </p>
                                    <p class="card-text fs-5 fw-bold mb-0">
                                        U$S {{ number_format($vehiculo->precio, 0, ',', '.') }}
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent border-0 pb-3">
                                    <a href="{{ route('vehiculos.show', $vehiculo) }}" class="btn btn-outline-dark btn-sm w-100">Ver detalle</a>
                                </div>
                            </div>
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
