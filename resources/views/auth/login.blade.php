@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">Iniciar sesión</h1>

                    <form action="{{ route('login.store') }}" method="POST" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" id="password" name="password" required
                                   class="form-control @error('password') is-invalid @enderror">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" id="recordarme" name="recordarme" value="1" class="form-check-input">
                            <label for="recordarme" class="form-check-label">Mantener la sesión iniciada</label>
                        </div>

                        <button type="submit" class="btn btn-dark w-100">Entrar</button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3">El catálogo se puede ver sin iniciar sesión.</p>
        </div>
    </div>
@endsection
