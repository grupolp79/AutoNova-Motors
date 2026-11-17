@extends('layouts.app')

@section('titulo', 'Precios de referencia')

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Herramientas del equipo</span>
            <h1>Precios de referencia</h1>
        </div>
    </div>

    <p class="text-muted">
        Tabla de referencia para fundamentar una cotización al cliente. Son valores de guía por marca, modelo y rango de años;
        no son el precio de venta de ningún vehículo.
    </p>

    {{-- Filtros: texto (marca o modelo) y año. La URL guarda lo que se buscó --}}
    <form method="GET" action="{{ route('precios.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-5">
            <label for="q" class="form-label mb-1">Marca o modelo</label>
            <input type="text" id="q" name="q" value="{{ $filtros['q'] ?? '' }}" maxlength="100"
                   class="form-control" placeholder="Escribí una marca o un modelo">
        </div>
        <div class="col-md-3">
            <label for="anio" class="form-label mb-1">Año</label>
            <input type="number" id="anio" name="anio" value="{{ $filtros['anio'] ?? '' }}" min="1900" max="2100"
                   class="form-control" placeholder="Por ejemplo: 2020">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-acento">Buscar</button>
            @if ($filtros)
                <a href="{{ route('precios.index') }}" class="btn btn-outline-dark">Quitar filtros</a>
            @endif
        </div>
    </form>

    <div class="card tarjeta-tabla mb-3">
        <table class="table tabla-autonova table-hover align-middle">
            <thead>
                <tr>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Años</th>
                    <th class="text-end">Precio de referencia</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($precios as $fila)
                    <tr>
                        <td class="fw-semibold">{{ $fila->marca }}</td>
                        <td>{{ $fila->modelo }}</td>
                        <td>{{ $fila->anio_desde }} a {{ $fila->anio_hasta }}</td>
                        <td class="text-end">U$S {{ number_format($fila->precio_referencia, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            {{ $total === 0 ? 'Todavía no hay precios de referencia cargados.' : 'No hay precios de referencia para esa búsqueda.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $precios->links() }}
@endsection
