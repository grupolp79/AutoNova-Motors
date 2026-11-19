{{--
    Bloque "Pedir turno" del detalle del vehículo (RF-13). Recibe $vehiculo y cambia según quién mira:
      visitante         -> invitación a iniciar sesión
      cliente           -> formulario con dos listas, Día y Hora (o aviso si ya tiene un turno activo)
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

                // Listas desplegables: solo días y horarios de atención (los valores están en el modelo Turno)
                $dias = Turno::diasParaElegir();
                $horarios = Turno::horarios();
                // Por día, los horarios que no se pueden elegir (ya pasaron o tienen un turno de este vehículo)
                $noDisponibles = Turno::horariosNoDisponibles($vehiculo, $dias);
                $nombresDia = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                // Día que aparece elegido: el que mandó antes (si volvió con error) o el primero de la lista
                $diaElegido = old('dia', $dias[0]->format('Y-m-d'));
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
                <form action="{{ route('turnos.store', $vehiculo) }}" method="POST" id="formTurno">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-7">
                            <label for="turnoDia" class="form-label">Día</label>
                            <select id="turnoDia" name="dia" required class="form-select @error('dia') is-invalid @enderror">
                                @foreach ($dias as $dia)
                                    <option value="{{ $dia->format('Y-m-d') }}" @selected($dia->format('Y-m-d') === $diaElegido)>
                                        {{ $nombresDia[$dia->dayOfWeekIso] }} {{ $dia->format('d/m') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-5">
                            <label for="turnoHora" class="form-label">Hora</label>
                            <select id="turnoHora" name="hora" required class="form-select @error('hora') is-invalid @enderror">
                                <option value="">Elegí</option>
                                {{-- Los horarios no disponibles del día elegido salen deshabilitados; el script de abajo
                                     los actualiza al cambiar de día --}}
                                @foreach ($horarios as $hora)
                                    <option value="{{ $hora }}" @selected(old('hora') === $hora)
                                            @disabled(in_array($hora, $noDisponibles[$diaElegido] ?? [], true))>{{ $hora }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @foreach (['dia', 'hora', 'fecha_hora'] as $campo)
                        @error($campo) <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                    @endforeach
                    <button type="submit" class="btn btn-acento">Pedir turno</button>
                </form>

                @push('scripts')
                    <script>
                        // Al cambiar el día, se deshabilitan los horarios que ese día no se pueden elegir.
                        // Es solo una ayuda: si alguien manda un horario ocupado igual, el servidor lo rechaza.
                        (function () {
                            const noDisponibles = @json($noDisponibles);
                            const dia = document.getElementById('turnoDia');
                            const hora = document.getElementById('turnoHora');

                            function actualizar() {
                                const ocupados = noDisponibles[dia.value] || [];
                                hora.querySelectorAll('option[value]:not([value=""])').forEach(function (opcion) {
                                    opcion.disabled = ocupados.includes(opcion.value);
                                    if (opcion.disabled && opcion.selected) hora.value = '';
                                });
                            }

                            dia.addEventListener('change', actualizar);
                        })();
                    </script>
                @endpush
            @endif
        @endif
    </div>
@endif
