@props(['estado'])
@php
    $c = [
        'pendiente' => ['insignia-gris', 'reloj'],
        'en_progreso' => ['insignia-azul', 'play'],
        'en_espera' => ['insignia-ambar', 'pausa'],
        'completada' => ['insignia-verde', 'check'],
        'cancelada' => ['insignia-gris', 'x'],
    ][$estado] ?? ['insignia-gris', 'reloj'];
@endphp
<span {{ $attributes->merge(['class' => $c[0]]) }}><x-icono :n="$c[1]" clase="size-3" />{{ \App\Models\OrdenTrabajo::ESTADOS[$estado] ?? $estado }}</span>
