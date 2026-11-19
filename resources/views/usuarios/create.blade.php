@extends('layouts.app')

@section('titulo', 'Crear usuario')

@section('contenido')
    <a href="{{ route('usuarios.index') }}" class="enlace-volver">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a usuarios
    </a>

    <div class="encabezado-pagina">
        <div>
            <span class="antetitulo">Administración</span>
            <h1>Crear usuario</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="alert alert-info" role="note">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <div>
                    Acá se crean cuentas de <strong>empleado</strong> o <strong>administrador</strong>. Los clientes crean su cuenta solos desde «Registrarse».
                    La persona puede cambiar la contraseña con «¿Olvidaste tu contraseña?».
                </div>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <form action="{{ route('usuarios.store') }}" method="POST" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre y apellido</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus maxlength="100"
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255"
                                   autocomplete="off" class="form-control @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol</label>
                            {{-- Solo empleado o admin; el servidor vuelve a controlar el valor --}}
                            <select id="rol" name="rol" required class="form-select @error('rol') is-invalid @enderror">
                                <option value="">Elegí un rol</option>
                                @foreach ($roles as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('rol') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                            @error('rol') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Contraseña provisoria</label>
                                <input type="password" id="password" name="password" required minlength="8" maxlength="72"
                                       autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                                <div class="form-text">Mínimo 8 caracteres.</div>
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">Repetí la contraseña</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required
                                       autocomplete="new-password" class="form-control">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-acento">Crear usuario</button>
                        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-dark">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
