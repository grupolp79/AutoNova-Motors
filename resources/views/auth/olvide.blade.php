@extends('layouts.app')

@section('titulo', '¿Olvidaste tu contraseña?')

@section('contenido')
    <div class="caja-login">
        <div class="card">
            <div class="cabecera-login">
                <span class="marca-icono"><i class="bi bi-key-fill" aria-hidden="true"></i></span>
                <h1 class="h4 mb-1">¿Olvidaste tu contraseña?</h1>
                <p class="small mb-0">AutoNova Motors</p>
            </div>

            <div class="card-body p-4">
                <p class="text-muted">
                    Escribí el correo con el que te registraste. Te mandamos un enlace para crear una contraseña nueva.
                </p>

                <form action="{{ route('password.email') }}" method="POST" novalidate>
                    @csrf
                    <div class="mb-4">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                               autocomplete="email" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-acento btn-lg w-100">Enviar enlace</button>
                </form>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">
            ¿Te acordaste? <a href="{{ route('login') }}">Volver a iniciar sesión</a>
        </p>
    </div>
@endsection
