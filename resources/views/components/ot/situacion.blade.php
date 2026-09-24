@props(['ot'])
@php
    $s = $ot->situacion();
    $c = [
        'atrasada' => 'insignia-roja', 'por_vencer' => 'insignia-ambar', 'en_tiempo' => 'insignia-verde',
        'sin_fecha' => 'insignia-gris', 'completada_a_tiempo' => 'insignia-verde', 'completada_tarde' => 'insignia-ambar',
        'cancelada' => 'insignia-gris',
    ][$s];
    $i = ['atrasada' => 'alerta', 'por_vencer' => 'reloj', 'completada_tarde' => 'reloj', 'sin_fecha' => 'calendario'][$s] ?? 'check';
@endphp
<span {{ $attributes->merge(['class' => $c]) }}><x-icono :n="$i" clase="size-3" />{{ \App\Models\OrdenTrabajo::SITUACIONES[$s] }}</span>
