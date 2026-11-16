{{-- Barra de progreso del ciclo de una consulta. Recibe $estado (nuevo, contactado, negociando o cerrado). --}}
@php
    $pasos = \App\Models\Lead::ESTADOS;
    $indice = array_search($estado, array_keys($pasos), true);
    $cerrada = $estado === 'cerrado';
@endphp
<ol class="progreso-lead" aria-label="Estado de la consulta">
    @foreach ($pasos as $clave => $texto)
        @php
            $hecho = $cerrada || $loop->index < $indice;
            $actual = ! $cerrada && $loop->index === $indice;
        @endphp
        <li class="paso @if ($hecho) hecho @elseif ($actual) actual @endif" @if ($actual) aria-current="step" @endif>
            <span class="punto">
                @if ($hecho) <i class="bi bi-check-lg" aria-hidden="true"></i> @else {{ $loop->iteration }} @endif
            </span>
            <span class="paso-texto">{{ $texto }}</span>
        </li>
    @endforeach
</ol>
