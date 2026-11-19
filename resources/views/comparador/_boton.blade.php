{{--
    Botón "Comparar" (RF-08), lo ven todos los perfiles. Recibe $vehiculo.
    Si el vehículo ya está elegido, el botón lo quita; si no, lo agrega.
    $formulario (opcional): id de un formulario que está en otro lugar de la página. Se usa en el catálogo,
    donde las tarjetas ya están dentro del formulario de filtros y HTML no permite un formulario dentro de otro.
--}}
@php
    $elegido = in_array($vehiculo->id, \App\Http\Controllers\ComparadorController::elegidos());
    $accion = $elegido ? route('comparador.quitar', $vehiculo->id) : route('comparador.agregar', $vehiculo);
    $clase = $elegido ? 'btn btn-sm btn-comparar elegido' : 'btn btn-sm btn-comparar';
@endphp

@if (isset($formulario))
    <button type="submit" form="{{ $formulario }}" class="{{ $clase }}" aria-pressed="{{ $elegido ? 'true' : 'false' }}">
        <i class="bi {{ $elegido ? 'bi-check2-square' : 'bi-layout-three-columns' }}" aria-hidden="true"></i>
        {{ $elegido ? 'Quitar de comparar' : 'Comparar' }}
    </button>
@else
    <form action="{{ $accion }}" method="POST" class="d-inline-block mb-3">
        @csrf
        <button type="submit" class="{{ $clase }}" aria-pressed="{{ $elegido ? 'true' : 'false' }}">
            <i class="bi {{ $elegido ? 'bi-check2-square' : 'bi-layout-three-columns' }}" aria-hidden="true"></i>
            {{ $elegido ? 'Quitar de comparar' : 'Comparar' }}
        </button>
    </form>
@endif
