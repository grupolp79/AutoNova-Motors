@extends('layouts.app')

@section('titulo', 'Usuarios')

@php
    // Color de la etiqueta de cada rol
    $color = ['admin' => 'danger', 'empleado' => 'primary', 'cliente' => 'secondary'];
    // Nombre en plural para las tarjetas de cantidad
    $plural = ['admin' => 'Administradores', 'empleado' => 'Empleados', 'cliente' => 'Clientes'];
    // Ícono de Bootstrap Icons de cada rol
    $icono = ['admin' => 'bi-shield-check', 'empleado' => 'bi-person-badge', 'cliente' => 'bi-people'];
@endphp

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Administración</span>
            <h1>Usuarios del sistema</h1>
        </div>
    </div>

    {{-- Cantidad por rol; cada tarjeta filtra la lista --}}
    <div class="row g-3 mb-4">
        @foreach ($roles as $r)
            <div class="col-md-4">
                <a href="{{ route('usuarios.index', ['rol' => $r]) }}" class="tarjeta-enlace text-decoration-none">
                    <div class="card tarjeta-dato h-100 {{ $rolActual === $r ? 'seleccionada' : '' }}">
                        <div class="card-body">
                            <span class="dato-icono"><i class="bi {{ $icono[$r] }}" aria-hidden="true"></i></span>
                            <div>
                                <div class="dato-etiqueta">{{ $plural[$r] }}</div>
                                <div class="dato-valor text-body">{{ $totales[$r] ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted">Mostrar:</span>
        <a href="{{ route('usuarios.index') }}" class="btn btn-sm filtro-pastilla {{ $rolActual === null ? 'btn-dark' : 'btn-outline-dark' }}">Todos</a>
        @foreach ($roles as $r)
            <a href="{{ route('usuarios.index', ['rol' => $r]) }}" class="btn btn-sm filtro-pastilla {{ $rolActual === $r ? 'btn-dark' : 'btn-outline-dark' }}">{{ ucfirst($r) }}</a>
        @endforeach
    </div>

    {{-- La tabla va dentro de una tarjeta; en celular se desplaza de costado --}}
    <div class="card tarjeta-tabla mb-3">
        <table class="table tabla-autonova table-hover align-middle">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Alta</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $u)
                    <tr>
                        <td class="fw-semibold">
                            {{ $u->name }}
                            @if ($u->id === auth()->id()) <span class="text-muted small fw-normal">(vos)</span> @endif
                        </td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge rounded-pill text-bg-{{ $color[$u->rol] ?? 'secondary' }}">{{ ucfirst($u->rol) }}</span></td>
                        <td class="text-muted">{{ $u->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No hay usuarios con ese rol.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $usuarios->links() }}
@endsection
