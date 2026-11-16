{{-- Badges de estado y destacado, usados en el catálogo y en el detalle.
     Los colores de cada estado están en autonova.css (.estado-disponible, .estado-reservado, .estado-vendido) --}}
<span class="badge badge-estado estado-{{ $vehiculo->estado }}">
    {{ \App\Models\Vehiculo::ESTADOS[$vehiculo->estado] ?? $vehiculo->estado }}
</span>
@if ($vehiculo->destacado)
    <span class="badge badge-destacado">★ Destacado</span>
@endif
