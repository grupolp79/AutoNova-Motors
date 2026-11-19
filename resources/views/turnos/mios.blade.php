@extends('layouts.app')

@section('titulo', 'Mis turnos')

@push('estilos')
    <link href="{{ asset('css/consultas.css') }}?v={{ filemtime(public_path('css/consultas.css')) }}" rel="stylesheet">
@endpush

@use('App\Models\Turno')

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Tu cuenta</span>
            <h1>Mis turnos</h1>
        </div>
    </div>

    @forelse ($turnos as $turno)
        @php $v = $turno->vehiculo; $portada = $v->fotos->first(); @endphp
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3">
                @if ($portada)
                    <img src="{{ $portada->url }}" class="miniatura-vehiculo" alt="{{ $v->marca }} {{ $v->modelo }}">
                @else
                    <div class="miniatura-vehiculo"></div>
                @endif

                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <a href="{{ route('vehiculos.show', $v) }}" class="fw-bold fs-5 text-decoration-none">{{ $v->marca }} {{ $v->modelo }}</a>
                            <div class="mt-1">
                                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                {{ $turno->fecha_hora->format('d/m/Y') }} a las {{ $turno->fecha_hora->format('H:i') }}
                            </div>
                        </div>
                        <span class="badge badge-turno turno-{{ $turno->estado }}">{{ Turno::ESTADOS[$turno->estado] }}</span>
                    </div>

                    @if ($turno->estado === 'pendiente')
                        <p class="small text-muted mt-2 mb-0">El equipo todavía tiene que confirmarlo. Te avisamos por la campanita.</p>
                    @endif

                    {{-- Cancelar: solo mientras el turno está activo (pendiente o confirmado) --}}
                    @if ($turno->puedePasarA('cancelado'))
                        <form action="{{ route('turnos.cancelar', $turno) }}" method="POST" class="mt-3"
                              onsubmit="return confirm('¿Seguro que querés cancelar este turno?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar turno
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-event display-5 text-muted" aria-hidden="true"></i>
                <h2 class="h5 mt-3">Todavía no pediste ningún turno</h2>
                <p class="text-muted">Elegí un vehículo disponible del catálogo y pedí el turno desde su página.</p>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-acento">Ver el catálogo</a>
            </div>
        </div>
    @endforelse

    {{ $turnos->links() }}
@endsection
