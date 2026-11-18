{{-- Campanita de avisos de la barra. Solo para usuarios con sesión (se incluye dentro de @auth).
     Muestra la cantidad de avisos sin leer y los 5 más nuevos; el resto está en «Ver todos». --}}
@php
    $usuarioActual = auth()->user();
    $noLeidas = $usuarioActual->unreadNotifications()->count();
    $recientes = $usuarioActual->notifications()->limit(5)->get();
@endphp
<li class="nav-item dropdown campanita">
    <a class="nav-link dropdown-toggle campanita-boton @if (request()->routeIs('notificaciones.*')) active @endif"
       href="#" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
       aria-label="Avisos, {{ $noLeidas === 0 ? 'ninguno sin leer' : $noLeidas . ' sin leer' }}">
        <i class="bi bi-bell-fill" aria-hidden="true"></i>
        <span class="d-lg-none ms-1">Avisos</span>
        @if ($noLeidas > 0)
            <span class="campanita-contador" aria-hidden="true">{{ $noLeidas > 9 ? '9+' : $noLeidas }}</span>
        @endif
    </a>
    <div class="dropdown-menu dropdown-menu-end campanita-menu">
        <div class="campanita-cabecera">
            <strong>Avisos</strong>
            @if ($noLeidas > 0)
                <form action="{{ route('notificaciones.leer-todas') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0">Marcar todos como leídos</button>
                </form>
            @endif
        </div>

        @forelse ($recientes as $aviso)
            @include('notificaciones._aviso', ['aviso' => $aviso])
        @empty
            <p class="campanita-vacia">No tenés avisos todavía.</p>
        @endforelse

        <a href="{{ route('notificaciones.index') }}" class="campanita-pie">Ver todos los avisos</a>
    </div>
</li>
