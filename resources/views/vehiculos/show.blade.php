@extends('layouts.app')

@section('titulo', $vehiculo->marca . ' ' . $vehiculo->modelo)

@section('contenido')
    <a href="{{ route('vehiculos.index') }}" class="btn btn-link px-0 mb-3">&larr; Volver al catálogo</a>

    <div class="row g-4">
        <div class="col-lg-7">
            @if ($vehiculo->fotos->isEmpty())
                <div class="bg-secondary-subtle d-flex align-items-center justify-content-center text-muted rounded"
                     style="height: 400px">Sin fotos</div>
            @else
                {{-- Carrusel de Bootstrap con todas las fotos, en el orden guardado --}}
                <div id="carruselFotos" class="carousel slide bg-dark rounded overflow-hidden">
                    <div class="carousel-indicators">
                        @foreach ($vehiculo->fotos as $i => $foto)
                            <button type="button" data-bs-target="#carruselFotos" data-bs-slide-to="{{ $i }}"
                                    class="{{ $i === 0 ? 'active' : '' }}" aria-label="Foto {{ $i + 1 }}"></button>
                        @endforeach
                    </div>

                    <div class="carousel-inner">
                        @foreach ($vehiculo->fotos as $i => $foto)
                            {{-- Solo la primera foto lleva la clase 'active' --}}
                            <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                                <img src="{{ $foto->ruta }}" class="d-block w-100 object-fit-cover" style="height: 400px"
                                     alt="Foto {{ $i + 1 }} de {{ $vehiculo->marca }} {{ $vehiculo->modelo }}">
                            </div>
                        @endforeach
                    </div>

                    <button class="carousel-control-prev" type="button" data-bs-target="#carruselFotos" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carruselFotos" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="mb-2">@include('vehiculos._badges')</div>
            <h1 class="h2">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</h1>
            <p class="fs-3 fw-bold">U$S {{ number_format($vehiculo->precio, 0, ',', '.') }}</p>

            <table class="table">
                <tr><th>Año</th><td>{{ $vehiculo->anio }}</td></tr>
                <tr><th>Kilometraje</th><td>{{ number_format($vehiculo->kilometraje, 0, ',', '.') }} km</td></tr>
                <tr><th>Transmisión</th><td>{{ \App\Models\Vehiculo::TRANSMISIONES[$vehiculo->transmision] ?? $vehiculo->transmision }}</td></tr>
                <tr><th>Combustible</th><td>{{ \App\Models\Vehiculo::COMBUSTIBLES[$vehiculo->combustible] ?? $vehiculo->combustible }}</td></tr>
            </table>

            {{-- Editar: Empleado y Admin. Eliminar: solo Admin. Un visitante o cliente no ve ninguno de los dos. --}}
            @auth
                <div class="d-flex gap-2">
                    @if (auth()->user()->puedeGestionar())
                        <a href="{{ route('vehiculos.edit', $vehiculo) }}" class="btn btn-primary">Editar</a>
                    @endif

                    @if (auth()->user()->esAdmin())
                        {{-- Los formularios HTML no soportan DELETE: @method lo simula y @csrf protege el envío --}}
                        <form action="{{ route('vehiculos.destroy', $vehiculo) }}" method="POST"
                              onsubmit="return confirm('¿Seguro que querés eliminar este vehículo? Esta acción no se puede deshacer.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>
    </div>
@endsection
