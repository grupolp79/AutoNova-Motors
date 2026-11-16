@extends('layouts.app')

@section('titulo', 'Acceso denegado')

@section('contenido')
    <div class="text-center py-5">
        <p class="display-1 fw-bold text-secondary mb-0">403</p>
        <h1 class="h3 mb-3">Acceso denegado</h1>
        <p class="text-muted">
            {{ $exception->getMessage() ?: 'No tenés permiso para ver esta página.' }}
            @auth
                <br>Tu rol actual es <strong>{{ auth()->user()->rol }}</strong>.
            @endauth
        </p>
        <a href="{{ route('vehiculos.index') }}" class="btn btn-dark">Volver al catálogo</a>
    </div>
@endsection
