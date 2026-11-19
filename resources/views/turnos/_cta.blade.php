{{--
    Bloque "Pedir turno" del detalle del vehículo (RF-13). Recibe $vehiculo y cambia según quién mira:
      visitante         -> invitación a iniciar sesión
      cliente           -> formulario con fecha y hora (o aviso si ya tiene un turno activo)
      empleado y admin  -> no se muestra (manejan los turnos desde la agenda)
    Solo se piden turnos de vehículos disponibles. El servidor vuelve a controlar todo (TurnoController).
--}}
@use('App\Models\Turno')
@php $usuario = auth()->user(); @endphp

@if (! $usuario || $usuario->rol === 'cliente')
    <div class="consulta-cta turno-cta" id="turno">
        @if ($vehiculo->estado !== 'disponible')
            <h2><i class="bi bi-calendar-x" aria-hidden="true"></i> Turnos de prueba</h2>
            <p class="mb-0 text-muted">Los turnos para conocer o probar un vehículo se piden solo cuando está disponible.</p>

        @elseif (! $usuario)
            <h2><i class="bi bi-calendar-event" aria-hidden="true"></i> ¿Querés conocerlo o probarlo?</h2>
            <p class="text-muted">Pedí un turno de lunes a sábado, de {{ Turno::HORA_APERTURA }} a {{ Turno::HORA_CIERRE }}. Necesitás una cuenta de cliente.</p>
            <a href="{{ route('login') }}" class="btn btn-outline-dark">Iniciar sesión para pedir turno</a>

        @else
            @php
                $activo = $usuario->turnos()->activos()->where('vehiculo_id', $vehiculo->id)->first();
                // Primer horario que se puede elegir: el próximo múltiplo de 30 minutos
                $desde = now()->startOfMinute();
                $desde->addMinutes(Turno::MINUTOS_POR_TURNO - $desde->minute % Turno::MINUTOS_POR_TURNO);
            @endphp

            @if ($activo)
                <h2><i class="bi bi-calendar-check" aria-hidden="true"></i> Ya tenés un turno para este vehículo</h2>
                <p class="mb-2">
                    {{ $activo->fecha_hora->format('d/m/Y H:i') }} ·
                    <span class="badge badge-turno turno-{{ $activo->estado }}">{{ Turno::ESTADOS[$activo->estado] }}</span>
                </p>
                <a href="{{ route('turnos.mios') }}" class="btn btn-outline-dark btn-sm">Ver mis turnos</a>
            @else
                <h2><i class="bi bi-calendar-event" aria-hidden="true"></i> Pedir turno</h2>
                <p class="text-muted small mb-2">
                    Para conocer o probar el vehículo. De lunes a sábado, de {{ Turno::HORA_APERTURA }} a {{ Turno::HORA_CIERRE }},
                    cada {{ Turno::MINUTOS_POR_TURNO }} minutos.
                </p>
                <form action="{{ route('turnos.store', $vehiculo) }}" method="POST">
                    @csrf
                    <label for="fecha_hora" class="form-label">Fecha y hora</label>
                    {{-- step en segundos: 1800 = 30 minutos --}}
                    <input type="datetime-local" id="fecha_hora" name="fecha_hora" required
                           min="{{ $desde->format('Y-m-d\TH:i') }}" step="{{ Turno::MINUTOS_POR_TURNO * 60 }}"
                           value="{{ old('fecha_hora') }}"
                           class="form-control mb-2 @error('fecha_hora') is-invalid @enderror">
                    @error('fecha_hora') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                    <button type="submit" class="btn btn-acento">Pedir turno</button>
                </form>
            @endif
        @endif
    </div>
@endif
