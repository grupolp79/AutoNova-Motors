@extends('layouts.app')

@section('titulo', 'Crear contraseña nueva')

@use('App\Http\Controllers\RecuperacionController')

@section('contenido')
    <div class="caja-login">
        <div class="card">
            {{-- Encabezado: solo el logo del Grupo LP de fondo, sin texto (igual que en Iniciar sesión) --}}
            <div class="cabecera-con-logo" role="img" aria-label="Logo de AutoNova Motors"></div>

            <div class="card-body p-4">
                <h1 class="titulo-formulario">Crear contraseña nueva</h1>

                <p class="text-muted">
                    Escribí tu correo, el código de 6 dígitos que te mandamos y la contraseña nueva.
                    El código vence en {{ RecuperacionController::MINUTOS_VALIDEZ }} minutos.
                </p>

                <form action="{{ route('password.update') }}" method="POST" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        {{-- Viene escrito desde la pantalla anterior (flash 'email') o de un intento fallido (old) --}}
                        <input type="email" id="email" name="email" value="{{ old('email', session('email')) }}" required
                               autocomplete="email" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código de 6 dígitos</label>
                        {{-- inputmode="numeric" abre el teclado de números en el celular; one-time-code permite
                             que el teléfono sugiera el código. No se vuelve a llenar después de un error. --}}
                        <input type="text" id="codigo" name="codigo" required autofocus
                               inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                               class="form-control @error('codigo') is-invalid @enderror">
                        @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña nueva</label>
                        <input type="password" id="password" name="password" required
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
        <p class="text-center text-muted small mt-3">
            ¿No te llegó o ya venció? <a href="{{ route('password.request') }}">Pedir un código nuevo</a>
        </p>
    </div>
@endsection
