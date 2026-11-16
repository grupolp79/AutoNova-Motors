@extends('layouts.app')

@section('titulo', 'Acceso denegado')

@section('contenido')
    <div class="pagina-error">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <span class="error-icono"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                <div class="error-codigo">ERROR 403</div>
                <h1 class="h3 mb-3">Acceso denegado</h1>
                <p class="text-muted mb-4">
                    {{ $exception->getMessage() ?: 'No tenés permiso para ver esta página.' }}
                    @auth
                        <br>Tu rol actual es <strong>{{ auth()->user()->rol }}</strong>.
                    @endauth
                </p>
                <a href="{{ route('vehiculos.index') }}" class="btn btn-dark">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al catálogo
                </a>
            </div>
        </div>
    </div>
@endsection
