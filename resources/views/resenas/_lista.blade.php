{{--
    "Reseñas de clientes" del detalle del vehículo (RF-14). Recibe $vehiculo.
    Muestra SOLO las aprobadas y, del cliente, solo el primer nombre (por privacidad).
    Si no hay ninguna aprobada, la sección no aparece.
--}}
@php
    $aprobadas = $vehiculo->resenas()->aprobadas()->with('cliente:id,name')->latest()->get();
@endphp

@if ($aprobadas->isNotEmpty())
    <section class="resenas-vehiculo" aria-labelledby="titulo-resenas">
        <h2 id="titulo-resenas"><i class="bi bi-chat-quote" aria-hidden="true"></i> Reseñas de clientes</h2>
        @foreach ($aprobadas as $resena)
            <figure class="resena-item">
                {{-- {{ }} escapa el texto: si alguien escribió HTML, se muestra como texto y no se ejecuta --}}
                <blockquote class="mb-1 texto-resena">{{ $resena->texto }}</blockquote>
                <figcaption class="small text-muted">
                    {{ $resena->primerNombre() }} · {{ $resena->created_at->format('d/m/Y') }}
                </figcaption>
            </figure>
        @endforeach
    </section>
@endif
