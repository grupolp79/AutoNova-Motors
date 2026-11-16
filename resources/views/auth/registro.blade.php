@extends('layouts.app')

@section('titulo', 'Crear cuenta')

@section('contenido')
    <div class="caja-login">
        <div class="card">
            <div class="cabecera-login">
                <span class="marca-icono"><i class="bi bi-person-plus-fill" aria-hidden="true"></i></span>
                <h1 class="h4 mb-1">Crear cuenta</h1>
                <p class="small mb-0">Para consultar por los vehículos de AutoNova Motors</p>
            </div>

            <div class="card-body p-4">
                <form action="{{ route('registro.store') }}" method="POST" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre y apellido</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus maxlength="100"
                               autocomplete="name" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255"
                               autocomplete="email" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" id="password" name="password" required minlength="8" maxlength="72"
                               autocomplete="new-password" aria-describedby="ayudaPassword"
                               class="form-control @error('password') is-invalid @enderror">
                        <div id="ayudaPassword" class="form-text">Mínimo 8 caracteres.</div>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Repetí la contraseña</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required
                               autocomplete="new-password" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-acento btn-lg w-100">Crear cuenta</button>
                </form>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">
            ¿Ya tenés cuenta? <a href="{{ route('login') }}">Iniciá sesión</a>
        </p>
    </div>
@endsection
