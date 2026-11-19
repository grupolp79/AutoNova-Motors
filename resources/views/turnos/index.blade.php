@extends('layouts.app')

@section('titulo', 'Turnos')

@push('estilos')
    <link href="{{ asset('css/consultas.css') }}?v={{ filemtime(public_path('css/consultas.css')) }}" rel="stylesheet">
@endpush

@use('App\Models\Turno')

@php
    // Ícono de cada estado y texto de cada botón de cambio de estado
    $icono = ['pendiente' => 'bi-hourglass-split', 'confirmado' => 'bi-calendar-check', 'cancelado' => 'bi-x-circle', 'realizado' => 'bi-check2-circle'];
    $botones = [
        'confirmado' => ['Confirmar', 'btn-dark'],
        'realizado' => ['Marcar realizado', 'btn-outline-dark'],
        'cancelado' => ['Cancelar', 'btn-outline-danger'],
    ];
@endphp

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Gestión</span>
            <h1>Agenda de turnos</h1>
        </div>
    </div>

    {{-- Cantidad por estado; cada tarjeta filtra la lista --}}
    <div class="row g-3 mb-4">
        @foreach (Turno::ESTADOS as $clave => $texto)
            <div class="col-6 col-lg-3">
                <a href="{{ route('turnos.index', ['estado' => $clave]) }}" class="tarjeta-enlace text-decoration-none">
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
        <a href="{{ route('turnos.index') }}" class="btn btn-sm filtro-pastilla {{ $estadoActual === null ? 'btn-dark' : 'btn-outline-dark' }}">Todos</a>
        @foreach (Turno::ESTADOS as $clave => $texto)
            <a href="{{ route('turnos.index', ['estado' => $clave]) }}" class="btn btn-sm filtro-pastilla {{ $estadoActual === $clave ? 'btn-dark' : 'btn-outline-dark' }}">{{ $texto }}s</a>
        @endforeach
    </div>

    <div class="card tarjeta-tabla mb-3">
        <table class="table tabla-autonova table-hover align-middle">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Cliente</th>
                    <th>Vehículo</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($turnos as $t)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ $t->fecha_hora->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="fw-semibold">{{ $t->cliente->name }}</div>
                            <div class="text-muted small">{{ $t->cliente->email }}</div>
                        </td>
                        <td>
                            <a href="{{ route('vehiculos.show', $t->vehiculo) }}" class="text-decoration-none">
                                {{ $t->vehiculo->marca }} {{ $t->vehiculo->modelo }}
                            </a>
                            <span class="text-muted">{{ $t->vehiculo->anio }}</span>
                        </td>
                        <td><span class="badge badge-turno turno-{{ $t->estado }}">{{ Turno::ESTADOS[$t->estado] }}</span></td>
                        <td class="text-end">
                            {{-- Un botón por cada estado al que puede pasar; cancelado y realizado son finales --}}
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                @forelse ($t->siguientes() as $estado)
                                    <form action="{{ route('turnos.estado', $t) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="estado" value="{{ $estado }}">
                                        <button type="submit" class="btn btn-sm {{ $botones[$estado][1] }}">{{ $botones[$estado][0] }}</button>
                                    </form>
                                @empty
                                    <span class="text-muted small">Sin acciones</span>
                                @endforelse
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay turnos con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $turnos->links() }}
@endsection
