@extends('layouts.app')

@section('titulo', 'Comparar vehículos')

@use('App\Http\Controllers\ComparadorController')
@use('App\Models\Vehiculo')

@section('contenido')
    @php
        // Marca de "mejor valor" para una celda: solo si ese vehículo tiene el mejor valor del dato
        $esMejor = fn (string $campo, $valor) => $mejores[$campo] !== null && (float) $valor == $mejores[$campo];
    @endphp

    <a href="{{ route('vehiculos.index') }}" class="enlace-volver">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al catálogo
    </a>

    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Comparador</span>
            <h1>Comparar vehículos</h1>
        </div>
        @if ($vehiculos->isNotEmpty())
            <form action="{{ route('comparador.vaciar') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-x-circle" aria-hidden="true"></i> Vaciar comparación
                </button>
            </form>
        @endif
    </div>

    @if ($vehiculos->count() < ComparadorController::MINIMO)
        {{-- Con 0 o 1 vehículo no hay nada para comparar: se explica y se vuelve al catálogo --}}
        <div class="card">
            <div class="card-body estado-vacio">
                <i class="bi bi-layout-three-columns" aria-hidden="true"></i>
                <h2 class="h5 mt-3">Elegí al menos {{ ComparadorController::MINIMO }} vehículos para comparar</h2>
                <p class="text-muted">
                    @if ($vehiculos->count() === 1)
                        Por ahora elegiste solo el {{ $vehiculos->first()->marca }} {{ $vehiculos->first()->modelo }}.
                    @endif
                    En el catálogo, cada vehículo tiene el botón «Comparar». Podés elegir hasta {{ ComparadorController::MAXIMO }}.
                </p>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-acento">Volver al catálogo</a>
            </div>
        </div>
    @else
        <p class="text-muted small">
            <span class="mejor-valor"><i class="bi bi-trophy" aria-hidden="true"></i> Mejor</span>
            marca el menor precio, el menor kilometraje y el año más nuevo.
        </p>

        {{-- Tabla lado a lado: una columna por vehículo. En el celular se desplaza de costado. --}}
        <div class="tarjeta-tabla table-responsive">
            <table class="table tabla-autonova tabla-comparar mb-0">
                <thead>
                    <tr>
                        <th scope="col" class="dato-nombre"><span class="visually-hidden">Dato</span></th>
                        @foreach ($vehiculos as $vehiculo)
                            <th scope="col">
                                <a href="{{ route('vehiculos.show', $vehiculo) }}" class="comparar-foto d-block">
                                    @if ($portada = $vehiculo->fotos->first())
                                        <img src="{{ $portada->ruta }}" alt="{{ $vehiculo->marca }} {{ $vehiculo->modelo }}">
                                    @else
                                        <span class="sin-foto"><i class="bi bi-camera" aria-hidden="true"></i> Sin foto</span>
                                    @endif
                                </a>
                                <a href="{{ route('vehiculos.show', $vehiculo) }}" class="comparar-titulo">
                                    {{ $vehiculo->marca }} {{ $vehiculo->modelo }}
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">Precio</th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>
                                <strong>U$S {{ number_format($vehiculo->precio, 0, ',', '.') }}</strong>
                                @if ($esMejor('precio', $vehiculo->precio))
                                    <span class="mejor-valor"><i class="bi bi-trophy" aria-hidden="true"></i> Mejor</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row">Año</th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>
                                {{ $vehiculo->anio }}
                                @if ($esMejor('anio', $vehiculo->anio))
                                    <span class="mejor-valor"><i class="bi bi-trophy" aria-hidden="true"></i> Mejor</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row">Kilometraje</th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>
                                {{ number_format($vehiculo->kilometraje, 0, ',', '.') }} km
                                @if ($esMejor('kilometraje', $vehiculo->kilometraje))
                                    <span class="mejor-valor"><i class="bi bi-trophy" aria-hidden="true"></i> Mejor</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row">Transmisión</th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>{{ Vehiculo::TRANSMISIONES[$vehiculo->transmision] ?? $vehiculo->transmision }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row">Combustible</th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>{{ Vehiculo::COMBUSTIBLES[$vehiculo->combustible] ?? $vehiculo->combustible }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        {{-- No hay columna "condición": se deduce del kilometraje, igual que en el filtro del catálogo --}}
                        <th scope="row">Condición</th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>{{ $vehiculo->kilometraje == 0 ? '0 km' : 'Usado' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row">Estado</th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>
                                <span class="badge badge-estado estado-{{ $vehiculo->estado }}">
                                    {{ Vehiculo::ESTADOS[$vehiculo->estado] ?? $vehiculo->estado }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row"><span class="visually-hidden">Acciones</span></th>
                        @foreach ($vehiculos as $vehiculo)
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('vehiculos.show', $vehiculo) }}" class="btn btn-dark btn-sm">Ver detalle</a>
                                    <form action="{{ route('comparador.quitar', $vehiculo->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-dark btn-sm">
                                            <i class="bi bi-x" aria-hidden="true"></i> Quitar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($vehiculos->count() < ComparadorController::MAXIMO)
            <p class="mt-3 text-muted small">
                Podés sumar un vehículo más desde el <a href="{{ route('vehiculos.index') }}">catálogo</a>.
            </p>
        @endif
    @endif
@endsection
