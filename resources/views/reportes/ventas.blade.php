@extends('layouts.app')

@section('titulo', 'Reporte de ventas')

@php
    // Formatos: dinero "U$S 12.345" y variación con signo explícito (+3 / −2 / sin cambio)
    $dinero = fn ($n) => 'U$S ' . number_format($n, 0, ',', '.');
    $signo = fn ($n) => $n > 0 ? '+' . number_format($n, 0, ',', '.') : ($n < 0 ? '−' . number_format(abs($n), 0, ',', '.') : '0');
    $pct = fn ($p) => $p === null ? 'sin base de comparación' : ($p > 0 ? '+' : ($p < 0 ? '−' : '')) . number_format(abs($p), 1, ',', '.') . ' %';
    $clase = fn ($n) => $n > 0 ? 'text-success' : ($n < 0 ? 'text-danger' : 'text-muted');
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Reporte de ventas por mes</h1>

        {{-- Selector del período: se envía por GET (?meses=3|6|12) --}}
        <form method="GET" action="{{ route('reportes.ventas') }}" class="d-flex align-items-center gap-2">
            <label for="meses" class="form-label mb-0">Mostrar</label>
            <select id="meses" name="meses" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach ($periodos as $p)
                    <option value="{{ $p }}" @selected($p === $meses)>últimos {{ $p }} meses</option>
                @endforeach
            </select>
            <noscript><button class="btn btn-sm btn-secondary">Ver</button></noscript>
        </form>
    </div>

    {{-- Resumen: mes actual contra el anterior --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Ventas de {{ ucfirst($actual['mes']->translatedFormat('F Y')) }} (mes actual)</div>
                    <div class="display-6 fw-bold">{{ $actual['ventas'] }}
                        <span class="fs-6 fw-normal text-muted">{{ $actual['ventas'] === 1 ? 'vehículo' : 'vehículos' }}</span>
                    </div>
                    <div class="{{ $clase($actual['variacion_ventas']) }}">
                        {{ $signo($actual['variacion_ventas']) }} frente a {{ $anterior ? ucfirst($anterior['mes']->translatedFormat('F')) : 'el mes anterior' }}
                        ({{ $pct($actual['variacion_ventas_pct']) }})
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Monto vendido en el mes actual</div>
                    <div class="display-6 fw-bold">{{ $dinero($actual['monto']) }}</div>
                    <div class="{{ $clase($actual['variacion_monto']) }}">
                        {{ $signo($actual['variacion_monto']) }} U$S frente al mes anterior
                        ({{ $pct($actual['variacion_monto_pct']) }})
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle bg-white">
            <thead class="table-dark">
                <tr>
                    <th>Mes</th>
                    <th class="text-end">Ventas</th>
                    <th class="text-end">Var. vs. mes anterior</th>
                    <th class="text-end">Monto vendido</th>
                    <th class="text-end">Var. monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ ucfirst($fila['mes']->translatedFormat('F Y')) }}</td>
                        <td class="text-end">{{ $fila['ventas'] }}</td>
                        <td class="text-end {{ $clase($fila['variacion_ventas']) }}">
                            {{ $signo($fila['variacion_ventas']) }}
                            @if ($fila['variacion_ventas_pct'] !== null) <small>({{ $pct($fila['variacion_ventas_pct']) }})</small> @endif
                        </td>
                        <td class="text-end">{{ $dinero($fila['monto']) }}</td>
                        <td class="text-end {{ $clase($fila['variacion_monto']) }}">
                            {{ $signo($fila['variacion_monto']) }} U$S
                            @if ($fila['variacion_monto_pct'] !== null) <small>({{ $pct($fila['variacion_monto_pct']) }})</small> @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td>Total del período</td>
                    <td class="text-end">{{ $filas->sum('ventas') }}</td>
                    <td></td>
                    <td class="text-end">{{ $dinero($filas->sum('monto')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="text-muted small">
        Una venta es un lead en estado «Cerrado»; el mes es el de su última actualización y el monto es el precio publicado
        del vehículo (el sistema no guarda un precio de venta aparte). Cuando el mes anterior no tuvo ventas no hay porcentaje de comparación.
    </p>
@endsection
