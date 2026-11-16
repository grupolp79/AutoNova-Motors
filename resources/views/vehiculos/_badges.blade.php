{{-- Badges de estado y destacado, usados en el catálogo y en el detalle --}}
@php
    // Color del badge según el estado
    $colores = ['disponible' => 'success', 'reservado' => 'warning', 'vendido' => 'secondary'];
@endphp
<span class="badge text-bg-{{ $colores[$vehiculo->estado] ?? 'secondary' }}">
    {{ \App\Models\Vehiculo::ESTADOS[$vehiculo->estado] ?? $vehiculo->estado }}
</span>
@if ($vehiculo->destacado)
    <span class="badge text-bg-primary">★ Destacado</span>
@endif
