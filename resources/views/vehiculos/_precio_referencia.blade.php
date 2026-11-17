{{--
    Comparación con el precio de referencia (RF-04). Solo lo ve el equipo (Empleado y Admin):
    es una ayuda para fundamentar una cotización, no información para el público.
    Recibe $vehiculo.
--}}
@auth
    @if (auth()->user()->puedeGestionar())
        @php
            $comparacion = $vehiculo->comparacionPrecio();
            $buscar = ['q' => $vehiculo->marca . ' ' . $vehiculo->modelo, 'anio' => $vehiculo->anio];
        @endphp
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-2"><i class="bi bi-graph-up" aria-hidden="true"></i> Precio de referencia</h2>

                @if ($comparacion)
                    @php
                        $texto = [
                            'por_debajo' => ['info', 'por debajo de la referencia'],
                            'en_linea' => ['success', 'en línea con la referencia'],
                            'por_encima' => ['warning', 'por encima de la referencia'],
                        ][$comparacion['veredicto']];
                    @endphp
                    <p class="mb-1">
                        Referencia para {{ $vehiculo->marca }} {{ $vehiculo->modelo }} {{ $vehiculo->anio }}:
                        <strong>U$S {{ number_format($comparacion['referencia'], 0, ',', '.') }}</strong>
                    </p>
                    <p class="mb-2">
                        <span class="badge text-bg-{{ $texto[0] }}">
                            @if ($comparacion['veredicto'] === 'en_linea')
                                {{ ucfirst($texto[1]) }}
                            @else
                                {{ number_format(abs($comparacion['porcentaje']), 1, ',', '.') }} % {{ $texto[1] }}
                            @endif
                        </span>
                        <span class="text-muted small">
                            (U$S {{ $comparacion['diferencia'] >= 0 ? '+' : '−' }}{{ number_format(abs($comparacion['diferencia']), 0, ',', '.') }})
                        </span>
                    </p>
                @else
                    <p class="text-muted mb-2">No hay un precio de referencia cargado para {{ $vehiculo->marca }} {{ $vehiculo->modelo }} {{ $vehiculo->anio }}.</p>
                @endif

                <a href="{{ route('precios.index', $buscar) }}" class="btn btn-outline-dark btn-sm">Ver tabla de referencia</a>
            </div>
        </div>
    @endif
@endauth
