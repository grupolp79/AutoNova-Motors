@extends('layouts.app')

@section('titulo', 'Crear contraseña nueva')

@section('contenido')
    <div class="caja-login">
        <div class="card">
            <div class="cabecera-login">
                <span class="marca-icono"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i></span>
                <h1 class="h4 mb-1">Crear contraseña nueva</h1>
                <p class="small mb-0">AutoNova Motors</p>
            </div>

            <div class="card-body p-4">
                {{-- Código inválido, vencido o ya usado: se explica y se ofrece pedir otro enlace --}}
                @error('email')
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div>
                            {{ $message }}
                            <a href="{{ route('password.request') }}" class="alert-link">Pedir otro enlace</a>
                        </div>
                    </div>
                @enderror

                <form action="{{ route('password.update') }}" method="POST" novalidate>
                    @csrf
                    {{-- El código y el correo vienen en el enlace del correo --}}
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ old('email', $email) }}">

                    <p class="text-muted small">Cuenta: <strong>{{ old('email', $email) }}</strong></p>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña nueva</label>
                        <input type="password" id="password" name="password" required autofocus
                               autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Al menos 8 caracteres.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Repetí la contraseña nueva</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required
                               autocomplete="new-password" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-acento btn-lg w-100">Guardar contraseña</button>
                </form>
            </div>
        </div>
    </div>
@endsection
