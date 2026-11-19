@extends('layouts.app')

@section('titulo', 'Reseñas')

@use('App\Models\Resena')

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Administración</span>
            <h1>Moderación de reseñas</h1>
        </div>
    </div>

    {{-- Cantidad por estado (de todas las reseñas) --}}
    <div class="row g-3 mb-4">
        @foreach (['pendiente' => ['Pendientes', 'bi-hourglass-split'], 'aprobada' => ['Aprobadas', 'bi-check2-circle'], 'rechazada' => ['Rechazadas', 'bi-x-circle']] as $clave => [$texto, $icono])
            <div class="col-12 col-sm-4">
                <div class="card tarjeta-dato h-100">
                    <div class="card-body">
                        <span class="dato-icono"><i class="bi {{ $icono }}" aria-hidden="true"></i></span>
                        <div>
                            <div class="dato-etiqueta">{{ $texto }}</div>
                            <div class="dato-valor text-body" data-total="{{ $clave }}">{{ $totales[$clave] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @forelse ($resenas as $resena)
        <div class="card mb-3 {{ $resena->estaPendiente() ? 'resena-por-moderar' : '' }}">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <a href="{{ route('vehiculos.show', $resena->vehiculo) }}" class="fw-bold text-decoration-none">
                            {{ $resena->vehiculo->marca }} {{ $resena->vehiculo->modelo }} {{ $resena->vehiculo->anio }}
                        </a>
                        <div class="text-muted small">
                            {{ $resena->cliente->name }} ({{ $resena->cliente->email }}) · {{ $resena->created_at->format('d/m/Y H:i') }}
                        </div>
                    </div>
                    <span class="badge badge-resena resena-{{ $resena->estado_moderacion }}">{{ Resena::MODERACION[$resena->estado_moderacion] }}</span>
                </div>

                <p class="mb-0 texto-resena">{{ $resena->texto }}</p>

                {{-- Solo las pendientes se moderan; una vez aprobada o rechazada no cambia más --}}
                @if ($resena->estaPendiente())
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @foreach (['aprobada' => ['Aprobar', 'btn-dark', 'bi-check-lg'], 'rechazada' => ['Rechazar', 'btn-outline-danger', 'bi-x-lg']] as $estado => [$boton, $clase, $icono])
                            <form action="{{ route('resenas.moderar', $resena) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="estado" value="{{ $estado }}">
                                <button type="submit" class="btn btn-sm {{ $clase }}">
                                    <i class="bi {{ $icono }}" aria-hidden="true"></i> {{ $boton }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body estado-vacio">
                <i class="bi bi-chat-quote" aria-hidden="true"></i>
                <h2 class="h5 mt-3">Todavía no hay reseñas</h2>
                <p class="text-muted mb-0">Cuando un cliente que compró deje su reseña, va a aparecer acá para moderarla.</p>
            </div>
        </div>
    @endforelse

    {{ $resenas->links() }}
@endsection
