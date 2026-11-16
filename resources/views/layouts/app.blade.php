<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Catálogo') - AutoNova Motors</title>
    {{-- Bootstrap 5.3 por CDN (sin npm ni Vite) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Cada vista puede agregar sus propios CSS acá con @push('estilos') --}}
    @stack('estilos')
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('vehiculos.index') }}">AutoNova Motors</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('vehiculos.index') }}">Catálogo</a>
                </li>

                @auth
                    {{-- Alta de vehículos: Empleado y Admin --}}
                    @if (auth()->user()->puedeGestionar())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('vehiculos.create') }}">+ Nuevo vehículo</a>
                        </li>
                    @endif

                    {{-- Reporte gerencial: solo Admin --}}
                    @if (auth()->user()->esAdmin())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('reportes.ventas') }}">Reporte de ventas</a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <span class="navbar-text ms-lg-3 me-lg-2">
                            {{ auth()->user()->name }}
                            <span class="badge text-bg-secondary">{{ ucfirst(auth()->user()->rol) }}</span>
                        </span>
                    </li>
                    <li class="nav-item">
                        {{-- Cerrar sesión es POST (con @csrf), no un link, para que no se pueda disparar desde otra página --}}
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">Iniciar sesión</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="container pb-5">
    {{-- Mensaje flash: el controlador lo guarda con ->with('success', ...) y dura un solo request --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Mensaje flash de error: ->with('error', ...) --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('contenido')
</main>

{{-- El bundle incluye Popper, necesario para el carrusel, el menú y los alerts --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
{{-- Scripts propios de cada vista (@push('scripts')) --}}
@stack('scripts')
</body>
</html>
