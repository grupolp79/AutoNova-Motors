@extends('layouts.app')

@section('titulo', 'Consultas')

@push('estilos')
    <link href="{{ asset('css/consultas.css') }}?v={{ filemtime(public_path('css/consultas.css')) }}" rel="stylesheet">
@endpush

@php
    // Ícono de Bootstrap Icons de cada estado
    $icono = ['nuevo' => 'bi-inbox', 'contactado' => 'bi-telephone', 'negociando' => 'bi-chat-dots', 'cerrado' => 'bi-check2-circle'];
    // Se conserva el filtro por vehículo al cambiar de estado
    $porVehiculo = $vehiculoFiltro ? ['vehiculo' => $vehiculoFiltro->id] : [];
@endphp

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Gestión</span>
            <h1>Consultas de clientes</h1>
        </div>
    </div>

    {{-- Cantidad por estado; cada tarjeta filtra la lista --}}
    <div class="row g-3 mb-4">
        @foreach (\App\Models\Lead::ESTADOS as $clave => $texto)
            <div class="col-6 col-lg-3">
                <a href="{{ route('consultas.index', ['estado' => $clave] + $porVehiculo) }}" class="tarjeta-enlace text-decoration-none">
                    <div class="card tarjeta-dato h-100 {{ $estadoActual === $clave ? 'seleccionada' : '' }}">
                        <div class="card-body">
                            <span class="dato-icono"><i class="bi {{ $icono[$clave] }}" aria-hidden="true"></i></span>
                            <div>
                                <div class="dato-etiqueta">{{ $texto }}s</div>
                                <div class="dato-valor text-body">{{ $totales[$clave] ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted">Mostrar:</span>
        <a href="{{ route('consultas.index', $porVehiculo) }}" class="btn btn-sm filtro-pastilla {{ $estadoActual === null ? 'btn-dark' : 'btn-outline-dark' }}">Todas</a>
        @foreach (\App\Models\Lead::ESTADOS as $clave => $texto)
            <a href="{{ route('consultas.index', ['estado' => $clave] + $porVehiculo) }}" class="btn btn-sm filtro-pastilla {{ $estadoActual === $clave ? 'btn-dark' : 'btn-outline-dark' }}">{{ $texto }}s</a>
        @endforeach

        @if ($vehiculoFiltro)
            <span class="ms-lg-3 badge text-bg-light border fs-6 fw-normal">
                Vehículo: {{ $vehiculoFiltro->marca }} {{ $vehiculoFiltro->modelo }}
                <a href="{{ route('consultas.index', $estadoActual ? ['estado' => $estadoActual] : []) }}" class="ms-1 text-decoration-none" aria-label="Quitar filtro de vehículo">✕</a>
            </span>
        @endif
    </div>

    <div class="card tarjeta-tabla mb-3">
        <table class="table tabla-autonova table-hover align-middle">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Vehículo</th>
                    <th>Estado</th>
                    <th class="text-center">Contactos</th>
                    <th>Recibida</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($consultas as $c)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $c->cliente->name }}</div>
                            <div class="text-muted small">{{ $c->cliente->email }}</div>
                        </td>
                        <td>
                            {{ $c->vehiculo->marca }} {{ $c->vehiculo->modelo }} <span class="text-muted">{{ $c->vehiculo->anio }}</span>
                            @if ($c->vehiculo->estado === 'vendido' && ! $c->estaCerrado())
                                <div><span class="badge badge-estado estado-vendido">Vehículo vendido</span></div>
                            @endif
                        </td>
                        <td><span class="badge badge-lead lead-{{ $c->estado }}">{{ \App\Models\Lead::ESTADOS[$c->estado] }}</span></td>
                        <td class="text-center">{{ $c->interacciones_count }}</td>
                        <td class="text-muted">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-end"><a href="{{ route('consultas.show', $c) }}" class="btn btn-outline-dark btn-sm">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay consultas con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $consultas->links() }}
@endsection
