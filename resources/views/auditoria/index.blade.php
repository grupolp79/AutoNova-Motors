@extends('layouts.app')

@section('titulo', 'Auditoría')

@use('App\Models\Auditoria')

@php
    // Enlace al elemento sobre el que se hizo la acción (si todavía existe una pantalla para verlo)
    $enlace = function ($r) {
        return match ($r->referencia_tipo) {
            'vehiculo' => $r->accion === 'vehiculo.eliminar' ? null : route('vehiculos.show', $r->referencia_id),
            'consulta' => route('consultas.show', $r->referencia_id),
            default => null,
        };
    };
@endphp

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Administración</span>
            <h1>Auditoría</h1>
        </div>
    </div>

    <p class="text-muted">Quién hizo qué, sobre qué y cuándo. Los registros no se pueden modificar ni borrar.</p>

    {{-- Filtro por GET: queda en la URL y se mantiene al cambiar de página --}}
    <form method="GET" action="{{ route('auditoria.index') }}" class="card mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-5">
                <label for="usuario" class="form-label">Usuario</label>
                <select id="usuario" name="usuario" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($usuarios as $u)
                        <option value="{{ $u->id }}" @selected($usuarioActual === $u->id)>{{ $u->name }} ({{ ucfirst($u->rol) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label for="accion" class="form-label">Acción</label>
                <select id="accion" name="accion" class="form-select">
                    <option value="">Todas</option>
                    @foreach (Auditoria::ACCIONES as $clave => $texto)
                        <option value="{{ $clave }}" @selected($accionActual === $clave)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark flex-grow-1">Filtrar</button>
                @if ($usuarioActual || $accionActual)
                    <a href="{{ route('auditoria.index') }}" class="btn btn-outline-dark" aria-label="Quitar filtros">✕</a>
                @endif
            </div>
        </div>
    </form>

    <div class="card tarjeta-tabla mb-3">
        <table class="table tabla-autonova align-middle">
            <thead>
                <tr>
                    <th>Cuándo</th>
                    <th>Quién</th>
                    <th>Qué</th>
                    <th>Sobre qué</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $r)
                    <tr>
                        <td class="text-nowrap">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($r->usuario)
                                <div class="fw-semibold">{{ $r->usuario->name }}</div>
                                <div class="text-muted small">{{ ucfirst($r->usuario->rol) }}</div>
                            @else
                                <span class="text-muted">Sin usuario</span>
                            @endif
                        </td>
                        <td>{{ Auditoria::ACCIONES[$r->accion] ?? $r->accion }}</td>
                        <td class="text-nowrap">
                            @if ($r->referencia_tipo)
                                @php $url = $enlace($r); @endphp
                                @if ($url)
                                    <a href="{{ $url }}">{{ Auditoria::TIPOS[$r->referencia_tipo] ?? $r->referencia_tipo }} #{{ $r->referencia_id }}</a>
                                @else
                                    {{ Auditoria::TIPOS[$r->referencia_tipo] ?? $r->referencia_tipo }} #{{ $r->referencia_id }}
                                @endif
                            @endif
                        </td>
                        <td class="small">{{ $r->detalle }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay registros con ese filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $registros->links() }}
@endsection
