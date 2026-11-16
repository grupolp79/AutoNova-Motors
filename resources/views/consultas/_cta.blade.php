{{--
    Bloque "consultar" del detalle del vehículo (RF-03). Recibe $vehiculo y cambia según quién mira:
      visitante         -> invitación a iniciar sesión
      cliente           -> formulario de consulta (o aviso si ya tiene una abierta)
      empleado y admin  -> cantidad de consultas del vehículo y enlace a la bandeja
    Si el vehículo ya se vendió, el cliente y el visitante ven un aviso en lugar del formulario.
--}}
@php $usuario = auth()->user(); @endphp
@push('estilos')
    <link href="{{ asset('css/consultas.css') }}?v={{ filemtime(public_path('css/consultas.css')) }}" rel="stylesheet">
@endpush

<div class="consulta-cta" id="consultar">
    @if ($usuario && $usuario->puedeGestionar())
        @php $cantidad = $vehiculo->leads()->count(); @endphp
        <h2><i class="bi bi-chat-dots" aria-hidden="true"></i> Consultas de este vehículo</h2>
        <p class="mb-2 text-muted">
            {{ $cantidad === 0 ? 'Todavía no recibió consultas.' : ($cantidad === 1 ? 'Recibió 1 consulta.' : "Recibió {$cantidad} consultas.") }}
        </p>
        @if ($cantidad > 0)
            <a href="{{ route('consultas.index', ['vehiculo' => $vehiculo->id]) }}" class="btn btn-outline-dark btn-sm">Ver consultas</a>
        @endif

    @elseif ($vehiculo->estado === 'vendido')
        <h2><i class="bi bi-tag" aria-hidden="true"></i> Vehículo vendido</h2>
        <p class="mb-0 text-muted">Este vehículo ya fue vendido. Mirá el resto del catálogo para encontrar otro.</p>

    @elseif (! $usuario)
        <h2><i class="bi bi-chat-dots" aria-hidden="true"></i> ¿Te interesa?</h2>
        <p class="text-muted">Para enviar una consulta por este vehículo necesitás una cuenta de cliente. Es gratis y se crea en un minuto.</p>
        <a href="{{ route('login') }}" class="btn btn-acento">Iniciar sesión para consultar</a>
        <a href="{{ route('registro') }}" class="btn btn-outline-dark">Crear cuenta</a>

    @else
        @php
            $abierta = $usuario->consultas()
                ->where('vehiculo_id', $vehiculo->id)
                ->where('estado', '!=', 'cerrado')
                ->first();
        @endphp

        @if ($abierta)
            <h2><i class="bi bi-check-circle" aria-hidden="true"></i> Ya consultaste por este vehículo</h2>
            <p class="mb-2">
                Estado de tu consulta:
                <span class="badge badge-lead lead-{{ $abierta->estado }}">{{ \App\Models\Lead::ESTADOS[$abierta->estado] }}</span>
            </p>
            <a href="{{ route('consultas.mias') }}" class="btn btn-outline-dark btn-sm">Ver mis consultas</a>
        @else
            <h2><i class="bi bi-chat-dots" aria-hidden="true"></i> Consultar por este vehículo</h2>
            <form action="{{ route('consultas.store', $vehiculo) }}" method="POST">
                @csrf
                <label for="mensaje" class="form-label">Mensaje (opcional)</label>
                <textarea id="mensaje" name="mensaje" rows="3" maxlength="500"
                          class="form-control mb-2 @error('mensaje') is-invalid @enderror"
                          placeholder="Por ejemplo: ¿se puede ver el fin de semana? ¿aceptan mi auto como parte de pago?">{{ old('mensaje') }}</textarea>
                @error('mensaje') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-acento">Enviar consulta</button>
            </form>
        @endif
    @endif
</div>
