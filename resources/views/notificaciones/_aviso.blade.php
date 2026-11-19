{{-- Un aviso de la campanita. Es un formulario POST (con @csrf) porque al tocarlo se marca como leído.
     Variable: $aviso (DatabaseNotification) --}}
@php
    $icono = match ($aviso->data['tipo'] ?? '') {
        'nueva_consulta' => 'bi-chat-dots-fill',
        'estado_consulta' => 'bi-arrow-right-circle-fill',
        'contacto_consulta' => 'bi-telephone-fill',
        'nuevo_turno' => 'bi-calendar-plus-fill',
        'estado_turno' => 'bi-calendar-check-fill',
        'nueva_resena' => 'bi-chat-quote-fill',
        'resena_moderada' => 'bi-patch-check-fill',
        default => 'bi-bell-fill',
    };
    $sinLeer = $aviso->read_at === null;
@endphp
<form action="{{ route('notificaciones.leer', $aviso->id) }}" method="POST" class="aviso-form">
    @csrf
    <button type="submit" class="aviso @if ($sinLeer) aviso-sin-leer @endif">
        <span class="aviso-icono" aria-hidden="true"><i class="bi {{ $icono }}"></i></span>
        <span class="aviso-cuerpo">
            <span class="aviso-titulo">{{ $aviso->data['titulo'] ?? 'Aviso' }}</span>
            <span class="aviso-texto">{{ $aviso->data['texto'] ?? '' }}</span>
            {{-- Menos de un minuto: «Recién» en vez de «hace 0 segundos» --}}
            <span class="aviso-hora">{{ $aviso->created_at->gt(now()->subMinute()) ? 'Recién' : $aviso->created_at->diffForHumans() }}</span>
        </span>
        @if ($sinLeer)
            <span class="aviso-punto" title="Sin leer"><span class="visually-hidden">Sin leer</span></span>
        @endif
    </button>
</form>
