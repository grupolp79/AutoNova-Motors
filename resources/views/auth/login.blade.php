@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <div class="caja-login">
        <div class="card">
            {{-- Cabecera con el degradé de la marca --}}
            <div class="cabecera-login">
                <span class="marca-icono"><i class="bi bi-car-front-fill" aria-hidden="true"></i></span>
                <h1 class="h4 mb-1">Iniciar sesión</h1>
                <p class="small mb-0">AutoNova Motors</p>
            </div>

            <div class="card-body p-4">
                <form action="{{ route('login.store') }}" method="POST" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                               autocomplete="email" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" id="password" name="password" required
                               autocomplete="current-password" class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                        <div class="form-check mb-0">
                            <input type="checkbox" id="recordarme" name="recordarme" value="1" class="form-check-input">
                            <label for="recordarme" class="form-check-label">Mantener la sesión iniciada</label>
                        </div>
                        <a href="{{ route('password.request') }}" class="small">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="btn btn-acento btn-lg w-100">Entrar</button>
                </form>
            </div>
        </div>
        <p class="text-center text-muted small mt-3 mb-1">
            ¿Sos cliente y todavía no tenés cuenta? <a href="{{ route('registro') }}">Crear cuenta</a>
        </p>
        <p class="text-center text-muted small">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            El catálogo se puede ver sin iniciar sesión.
        </p>
    </div>
@endsection
