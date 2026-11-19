@extends('layouts.app')

@section('titulo', 'Mis consultas')

@push('estilos')
    <link href="{{ asset('css/consultas.css') }}?v={{ filemtime(public_path('css/consultas.css')) }}" rel="stylesheet">
@endpush

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Tu cuenta</span>
            <h1>Mis consultas</h1>
        </div>
    </div>

    @forelse ($consultas as $consulta)
        @php $v = $consulta->vehiculo; $portada = $v->fotos->first(); @endphp
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3">
                    @if ($portada)
                        <img src="{{ $portada->url }}" class="miniatura-vehiculo" alt="{{ $v->marca }} {{ $v->modelo }}">
                    @else
                        <div class="miniatura-vehiculo"></div>
                    @endif

                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <a href="{{ route('vehiculos.show', $v) }}" class="fw-bold fs-5 text-decoration-none">{{ $v->marca }} {{ $v->modelo }}</a>
                                <div class="text-muted small">
                                    {{ $v->anio }} · U$S {{ number_format($v->precio, 0, ',', '.') }} ·
                                    Enviada el {{ $consulta->created_at->format('d/m/Y') }}
                                </div>
                            </div>
                            <span class="badge badge-lead lead-{{ $consulta->estado }}">{{ \App\Models\Lead::ESTADOS[$consulta->estado] }}</span>
                        </div>

                        @if ($consulta->mensaje)
                            <p class="mt-2 mb-0 fst-italic text-muted">“{{ $consulta->mensaje }}”</p>
                        @endif

                        @if ($consulta->interacciones_count > 0)
                            <p class="mt-2 mb-0 small">
                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                El equipo se comunicó con vos {{ $consulta->interacciones_count === 1 ? '1 vez' : $consulta->interacciones_count . ' veces' }}.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="mt-3">
                    @include('consultas._progreso', ['estado' => $consulta->estado])
                </div>

                {{-- Reseña (RF-14): solo en las consultas cerradas, es decir, en las compras --}}
                @if ($consulta->estaCerrado())
                    @php $resena = $resenas[$consulta->vehiculo_id] ?? null; @endphp
                    <div class="resena-compra mt-3">
                        @if ($resena)
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold"><i class="bi bi-chat-quote" aria-hidden="true"></i> Tu reseña</span>
                                <span class="badge badge-resena resena-{{ $resena->estado_moderacion }}">{{ \App\Models\Resena::MODERACION[$resena->estado_moderacion] }}</span>
                            </div>
                            <p class="mb-1 texto-resena">{{ $resena->texto }}</p>
                            <p class="small text-muted mb-0">
                                @switch($resena->estado_moderacion)
                                    @case('pendiente') Se va a ver en la página del vehículo cuando el administrador la apruebe. @break
                                    @case('aprobada') Ya se ve en la página del vehículo. ¡Gracias! @break
                                    @default El administrador decidió no publicarla.
                                @endswitch
                            </p>
                        @else
                            <span class="me-2">¿Cómo fue tu compra?</span>
                            <a href="{{ route('resenas.create', $consulta) }}" class="btn btn-acento btn-sm">
                                <i class="bi bi-chat-quote" aria-hidden="true"></i> Dejar reseña
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-chat-dots display-5 text-muted" aria-hidden="true"></i>
                <h2 class="h5 mt-3">Todavía no consultaste por ningún vehículo</h2>
                <p class="text-muted">Elegí uno del catálogo y enviá tu consulta desde su página.</p>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-acento">Ver el catálogo</a>
            </div>
        </div>
    @endforelse

    {{ $consultas->links() }}
@endsection
