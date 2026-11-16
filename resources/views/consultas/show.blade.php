@extends('layouts.app')

@section('titulo', 'Consulta #' . $consulta->id)

@push('estilos')
    <link href="{{ asset('css/consultas.css') }}?v={{ filemtime(public_path('css/consultas.css')) }}" rel="stylesheet">
@endpush

@php
    $v = $consulta->vehiculo;
    $portada = $v->fotos->first();
    $siguiente = $consulta->siguienteEstado();
    $vendidoPorOtra = $v->estado === 'vendido' && ! $consulta->estaCerrado();
    // Ícono de cada tipo de contacto
    $iconoTipo = ['llamada' => 'bi-telephone', 'correo' => 'bi-envelope', 'visita' => 'bi-geo-alt'];
@endphp

@section('contenido')
    <a href="{{ route('consultas.index') }}" class="btn btn-link px-0 mb-2">&larr; Volver a las consultas</a>

    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Consulta #{{ $consulta->id }}</span>
            <h1>{{ $consulta->cliente->name }} · {{ $v->marca }} {{ $v->modelo }}</h1>
        </div>
        <span class="badge badge-lead lead-{{ $consulta->estado }} fs-6">{{ \App\Models\Lead::ESTADOS[$consulta->estado] }}</span>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            {{-- Cliente y mensaje --}}
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted">Cliente</h2>
                    <div class="fw-semibold">{{ $consulta->cliente->name }}</div>
                    <a href="mailto:{{ $consulta->cliente->email }}">{{ $consulta->cliente->email }}</a>
                    <div class="text-muted small mt-1">Consulta recibida el {{ $consulta->created_at->format('d/m/Y H:i') }}</div>

                    <h2 class="h6 text-uppercase text-muted mt-4">Mensaje del cliente</h2>
                    @if ($consulta->mensaje)
                        <p class="mb-0">{{ $consulta->mensaje }}</p>
                    @else
                        <p class="mb-0 text-muted">El cliente no dejó mensaje.</p>
                    @endif
                </div>
            </div>

            {{-- Vehículo --}}
            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap gap-3">
                    @if ($portada)
                        <img src="{{ $portada->ruta }}" class="miniatura-vehiculo" alt="{{ $v->marca }} {{ $v->modelo }}">
                    @endif
                    <div>
                        <h2 class="h6 text-uppercase text-muted">Vehículo</h2>
                        <a href="{{ route('vehiculos.show', $v) }}" class="fw-bold text-decoration-none">{{ $v->marca }} {{ $v->modelo }} {{ $v->anio }}</a>
                        <div>U$S {{ number_format($v->precio, 0, ',', '.') }}</div>
                        <span class="badge badge-estado estado-{{ $v->estado }}">{{ \App\Models\Vehiculo::ESTADOS[$v->estado] }}</span>
                    </div>
                </div>
            </div>

            {{-- Estado y avance --}}
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Avance de la consulta</h2>
                    @include('consultas._progreso', ['estado' => $consulta->estado])

                    <div class="mt-4">
                        @if ($consulta->estaCerrado())
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                <div>Consulta cerrada: el vehículo figura como vendido. Queda como registro de la venta.</div>
                            </div>
                        @elseif ($vendidoPorOtra)
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                <div>Este vehículo ya figura como vendido, así que la consulta no se puede avanzar.</div>
                            </div>
                        @else
                            {{-- PATCH no existe en los formularios HTML: @method lo simula; @csrf protege el envío --}}
                            <form action="{{ route('consultas.avanzar', $consulta) }}" method="POST"
                                  @if ($siguiente === 'cerrado') onsubmit="return confirm('Al cerrar la consulta el vehículo pasa a Vendido. ¿Confirmás?');" @endif>
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-acento">
                                    Pasar a {{ \App\Models\Lead::ESTADOS[$siguiente] }}
                                </button>
                                @if ($siguiente === 'negociando')
                                    <div class="form-text">Al pasar a Negociando, el vehículo queda Reservado.</div>
                                @elseif ($siguiente === 'cerrado')
                                    <div class="form-text">Cerrar la consulta significa que se concretó la venta: el vehículo pasa a Vendido.</div>
                                @endif
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Historial de contactos (RF-09) --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Historial de contactos</h2>

                    @forelse ($consulta->interacciones as $i)
                        @if ($loop->first) <ul class="linea-tiempo"> @endif
                        <li>
                            <span class="icono-contacto"><i class="bi {{ $iconoTipo[$i->tipo] }}" aria-hidden="true"></i></span>
                            <div class="contacto-titulo">{{ \App\Models\Interaccion::TIPOS[$i->tipo] }}</div>
                            <div class="contacto-fecha">{{ $i->fecha->format('d/m/Y H:i') }}</div>
                            @if ($i->comentario) <div class="mt-1">{{ $i->comentario }}</div> @endif
                        </li>
                        @if ($loop->last) </ul> @endif
                    @empty
                        <p class="text-muted">Todavía no hay contactos registrados.</p>
                    @endforelse

                    @if (! $consulta->estaCerrado())
                        <hr>
                        <h2 class="h6 mb-3">Registrar un contacto</h2>
                        <form action="{{ route('consultas.interacciones.store', $consulta) }}" method="POST">
                            @csrf
                            <div class="row g-2 mb-2">
                                <div class="col-sm-5">
                                    <label for="tipo" class="form-label">Tipo</label>
                                    <select id="tipo" name="tipo" required class="form-select @error('tipo') is-invalid @enderror">
                                        @foreach (\App\Models\Interaccion::TIPOS as $clave => $texto)
                                            <option value="{{ $clave }}" @selected(old('tipo') === $clave)>{{ $texto }}</option>
                                        @endforeach
                                    </select>
                                    @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-7">
                                    <label for="fecha" class="form-label">Fecha y hora</label>
                                    <input type="datetime-local" id="fecha" name="fecha"
                                           value="{{ old('fecha', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}"
                                           class="form-control @error('fecha') is-invalid @enderror">
                                    @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <label for="comentario" class="form-label">Comentario (opcional)</label>
                            <textarea id="comentario" name="comentario" rows="3" maxlength="500"
                                      class="form-control mb-2 @error('comentario') is-invalid @enderror">{{ old('comentario') }}</textarea>
                            @error('comentario') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                            <button type="submit" class="btn btn-dark">Guardar contacto</button>
                        </form>
                    @else
                        <hr>
                        <p class="text-muted mb-0">La consulta está cerrada: no se agregan más contactos.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
