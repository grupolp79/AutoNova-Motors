<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Catálogo') - AutoNova Motors</title>
    {{-- Bootstrap 5.3 y Bootstrap Icons por CDN (sin npm ni Vite) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Tipografía de Google Fonts; si no carga, autonova.css usa la fuente del sistema --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Estilos propios. El ?v= cambia cuando se modifica el archivo, así el navegador no usa una copia vieja --}}
    <link href="{{ asset('css/autonova.css') }}?v={{ filemtime(public_path('css/autonova.css')) }}" rel="stylesheet">
    {{-- Cada vista puede agregar sus propios CSS acá con @push('estilos') --}}
    @stack('estilos')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-autonova sticky-top mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('vehiculos.index') }}">
            <span class="marca-icono"><i class="bi bi-car-front-fill" aria-hidden="true"></i></span>
            AutoNova Motors
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                aria-controls="menu" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            {{-- routeIs() marca como activo el enlace de la página en la que se está --}}
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 py-2 py-lg-0">
                <li class="nav-item">
                    <a class="nav-link @if (request()->routeIs('vehiculos.index', 'vehiculos.show')) active @endif"
                       @if (request()->routeIs('vehiculos.index')) aria-current="page" @endif
                       href="{{ route('vehiculos.index') }}">Catálogo</a>
                </li>

                {{-- Comparador (RF-08): todos los perfiles; aparece cuando hay al menos un vehículo elegido --}}
                @php $paraComparar = count(\App\Http\Controllers\ComparadorController::elegidos()); @endphp
                @if ($paraComparar > 0)
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('comparador.index')) active @endif"
                           href="{{ route('comparador.index') }}">
                            <i class="bi bi-layout-three-columns" aria-hidden="true"></i> Comparar ({{ $paraComparar }})
                        </a>
                    </li>
                @endif

                @auth
                    {{-- Alta de vehículos: Empleado y Admin --}}
                    @if (auth()->user()->puedeGestionar())
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('vehiculos.create')) active @endif"
                               href="{{ route('vehiculos.create') }}">+ Nuevo vehículo</a>
                        </li>
                    @endif

                    {{-- Cliente: sus consultas. Empleado y Admin: bandeja de consultas, con el aviso de las nuevas --}}
                    @if (auth()->user()->rol === 'cliente')
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('consultas.mias')) active @endif"
                               href="{{ route('consultas.mias') }}">Mis consultas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('favoritos.index')) active @endif"
                               href="{{ route('favoritos.index') }}">Favoritos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('turnos.mios')) active @endif"
                               href="{{ route('turnos.mios') }}">Mis turnos</a>
                        </li>
                    @elseif (auth()->user()->puedeGestionar())
                        @php $nuevas = \App\Models\Lead::where('estado', 'nuevo')->count(); @endphp
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('consultas.*')) active @endif"
                               href="{{ route('consultas.index') }}">
                                Consultas
                                @if ($nuevas > 0) <span class="badge rounded-pill text-bg-danger">{{ $nuevas }}</span> @endif
                            </a>
                        </li>
                        {{-- Agenda de turnos (RF-13), con la cantidad de turnos pendientes de confirmar --}}
                        @php $turnosPendientes = \App\Models\Turno::where('estado', 'pendiente')->count(); @endphp
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('turnos.index')) active @endif"
                               href="{{ route('turnos.index') }}">
                                Turnos
                                @if ($turnosPendientes > 0) <span class="badge rounded-pill text-bg-warning">{{ $turnosPendientes }}</span> @endif
                            </a>
                        </li>
                    @endif

                    {{-- Precios de referencia (RF-04): Empleado y Admin --}}
                    @if (auth()->user()->puedeGestionar())
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('precios.index')) active @endif"
                               href="{{ route('precios.index') }}">Precios de referencia</a>
                        </li>
                    @endif

                    {{-- Reporte gerencial, moderación de reseñas y listado de usuarios: solo Admin --}}
                    @if (auth()->user()->esAdmin())
                        @php $resenasPendientes = \App\Models\Resena::where('estado_moderacion', 'pendiente')->count(); @endphp
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('resenas.index')) active @endif"
                               href="{{ route('resenas.index') }}">
                                Reseñas
                                @if ($resenasPendientes > 0) <span class="badge rounded-pill text-bg-warning">{{ $resenasPendientes }}</span> @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('reportes.ventas')) active @endif"
                               href="{{ route('reportes.ventas') }}">Reporte de ventas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('usuarios.index')) active @endif"
                               href="{{ route('usuarios.index') }}">Usuarios</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs('auditoria.index')) active @endif"
                               href="{{ route('auditoria.index') }}">Auditoría</a>
                        </li>
                    @endif

                    {{-- Campanita de avisos (los tres roles) --}}
                    @include('layouts._campanita')

                    <li class="nav-item">
                        <span class="navbar-text d-inline-flex align-items-center gap-2 ms-lg-3 me-lg-2 my-2 my-lg-0">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            {{ auth()->user()->name }}
                            <span class="badge badge-rol rol-{{ auth()->user()->rol }}">{{ ucfirst(auth()->user()->rol) }}</span>
                        </span>
                    </li>
                    <li class="nav-item">
                        {{-- Cerrar sesión es POST (con @csrf), no un link, para que no se pueda disparar desde otra página --}}
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión
                            </button>
                        </form>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('login')) active @endif" href="{{ route('login') }}">
                            <i class="bi bi-person" aria-hidden="true"></i> Iniciar sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('registro')) active @endif" href="{{ route('registro') }}">
                            <i class="bi bi-person-plus" aria-hidden="true"></i> Registrarse
                        </a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="container pb-5">
    {{-- Mensaje flash: el controlador lo guarda con ->with('success', ...) y dura un solo request --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- Mensaje flash de error: ->with('error', ...) --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @yield('contenido')
</main>

<footer class="pie">
    <div class="container text-center">
        <i class="bi bi-car-front-fill" aria-hidden="true"></i>
        AutoNova Motors · Servicio Automotriz
    </div>
</footer>

{{-- El bundle incluye Popper, necesario para el carrusel, el menú y los alerts --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
{{-- Scripts propios de cada vista (@push('scripts')) --}}
@stack('scripts')
</body>
</html>
