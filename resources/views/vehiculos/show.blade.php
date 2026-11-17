@extends('layouts.app')

@section('titulo', $vehiculo->marca . ' ' . $vehiculo->modelo)

@php
    // Vista de gestión: Empleado y Admin ven además el panel "Gestión".
    // Es solo lo que se muestra: los permisos se controlan en el servidor (middleware del controlador).
    $usuario = auth()->user();
    $gestion = $usuario?->puedeGestionar() ?? false;
    $esAdmin = $usuario?->esAdmin() ?? false;

    // Datos del vehículo para la grilla de tarjetitas: [ícono, etiqueta, valor]
    $datos = [
        ['bi-calendar3', 'Año', $vehiculo->anio],
        ['bi-speedometer2', 'Kilometraje', number_format($vehiculo->kilometraje, 0, ',', '.') . ' km'],
        ['bi-gear', 'Transmisión', \App\Models\Vehiculo::TRANSMISIONES[$vehiculo->transmision] ?? $vehiculo->transmision],
        [$vehiculo->combustible === 'electrico' ? 'bi-lightning-charge' : 'bi-fuel-pump', 'Combustible',
            \App\Models\Vehiculo::COMBUSTIBLES[$vehiculo->combustible] ?? $vehiculo->combustible],
    ];
@endphp

@section('contenido')
    <a href="{{ route('vehiculos.index') }}" class="enlace-volver">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al catálogo
    </a>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            @if ($vehiculo->fotos->isEmpty())
                <div class="foto-vacia d-flex flex-column align-items-center justify-content-center text-muted">
                    <i class="bi bi-camera fs-1" aria-hidden="true"></i>
                    Sin fotos
                </div>
            @else
                {{-- Carrusel de Bootstrap con todas las fotos, en el orden guardado --}}
                <div id="carruselFotos" class="carousel slide carrusel-detalle">
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
                                <img src="{{ $foto->ruta }}" class="d-block"
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
            <h1 class="mb-1">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</h1>
            <p class="precio-detalle">U$S {{ number_format($vehiculo->precio, 0, ',', '.') }}</p>

            {{-- ===== Panel "Gestión" (solo Empleado y Admin; para el resto no existe en el HTML) ===== --}}
            @if ($gestion)
                <section class="card panel-gestion color-{{ $vehiculo->estado }}" aria-label="Gestión del vehículo">
                    <div class="card-body">
                        <span class="etiqueta-gestion mb-3">
                            <i class="bi bi-sliders" aria-hidden="true"></i> Gestión
                        </span>
                        <dl>
                            <dt>Estado</dt>
                            <dd>
                                <span class="badge badge-estado">{{ \App\Models\Vehiculo::ESTADOS[$vehiculo->estado] ?? $vehiculo->estado }}</span>
                            </dd>
                            <dt>Destacado</dt>
                            <dd>{{ $vehiculo->destacado ? '★ Sí, aparece primero en el catálogo' : 'No' }}</dd>
                            <dt>Alta</dt>
                            <dd>{{ $vehiculo->created_at?->format('d/m/Y H:i') }}</dd>
                            <dt>Última modificación</dt>
                            <dd>{{ $vehiculo->updated_at?->format('d/m/Y H:i') }}</dd>
                        </dl>

                        {{-- Editar: Empleado y Admin. Eliminar: solo Admin. --}}
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('vehiculos.edit', $vehiculo) }}" class="btn btn-dark">
                                <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                            </a>

                            @if ($esAdmin)
                                {{-- Los formularios HTML no soportan DELETE: @method lo simula y @csrf protege el envío --}}
                                <form action="{{ route('vehiculos.destroy', $vehiculo) }}" method="POST"
                                      onsubmit="return confirm('¿Seguro que querés eliminar este vehículo? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i> Eliminar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            {{-- Descripción completa (o el texto armado con los datos si está vacía) --}}
            <p class="detalle-descripcion">{{ $vehiculo->resumen }}</p>

            <div class="datos-grilla">
                @foreach ($datos as [$icono, $etiqueta, $valor])
                    <div class="dato-mini">
                        <i class="bi {{ $icono }}" aria-hidden="true"></i>
                        <div>
                            <div class="dato-mini-etiqueta">{{ $etiqueta }}</div>
                            <div class="dato-mini-valor">{{ $valor }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Favoritos (RF-11): el cliente guarda el vehículo; el visitante recibe la invitación a iniciar sesión --}}
            @include('favoritos._boton')

            {{-- Precio de referencia (RF-04): solo lo ve el equipo --}}
            @include('vehiculos._precio_referencia')

            {{-- Consultar por el vehículo (RF-03): el bloque cambia según el rol de quien mira --}}
            @include('consultas._cta')
        </div>
    </div>
@endsection
