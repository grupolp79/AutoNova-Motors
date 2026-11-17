{{--
    Botón de favoritos del detalle del vehículo (RF-11). Recibe $vehiculo.
      visitante          -> invitación a iniciar sesión
      cliente            -> agregar o quitar de favoritos
      empleado y admin   -> no se muestra
--}}
@php $usuario = auth()->user(); @endphp
@if (! $usuario)
    <a href="{{ route('login') }}" class="btn btn-outline-dark btn-sm mb-3">
        <i class="bi bi-heart" aria-hidden="true"></i> Guardar en favoritos
    </a>
@elseif ($usuario->rol === 'cliente')
    @if ($usuario->favoritos()->where('vehiculos.id', $vehiculo->id)->exists())
        <form action="{{ route('favoritos.destroy', $vehiculo) }}" method="POST" class="d-inline-block mb-3">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-heart-fill" aria-hidden="true"></i> Quitar de favoritos
            </button>
        </form>
    @else
        <form action="{{ route('favoritos.store', $vehiculo) }}" method="POST" class="d-inline-block mb-3">
            @csrf
            <button type="submit" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-heart" aria-hidden="true"></i> Guardar en favoritos
            </button>
        </form>
    @endif
@endif
