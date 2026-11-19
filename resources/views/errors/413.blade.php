{{--
    Error 413: el formulario mandó más datos de los que acepta PHP (post_max_size), por ejemplo
    muchas fotos pesadas juntas. Laravel corta el pedido antes de validarlo (ValidatePostSize),
    así que en lugar de una pantalla en blanco o un error 500 se muestra esta explicación.
--}}
@extends('layouts.app')

@section('titulo', 'Las fotos pesan demasiado')

@section('contenido')
    <div class="pagina-error">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <span class="error-icono"><i class="bi bi-images" aria-hidden="true"></i></span>
                <div class="error-codigo">ERROR 413</div>
                <h1 class="h3 mb-3">Las fotos pesan demasiado</h1>
                <p class="text-muted mb-4">
                    Entre todas, las fotos que elegiste superan lo que el servidor acepta en un solo envío,
                    así que no se guardó nada. Volvé al formulario y subí menos fotos por vez
                    (cada una de hasta 5 MB).
                </p>
                <a href="{{ url()->previous(route('vehiculos.index')) }}" class="btn btn-dark">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al formulario
                </a>
            </div>
        </div>
    </div>
@endsection
