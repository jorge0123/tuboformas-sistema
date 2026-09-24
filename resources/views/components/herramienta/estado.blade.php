@props(['estado'])
@php
    $c = ['disponible' => 'insignia-verde', 'asignada' => 'insignia-azul', 'en_reparacion' => 'insignia-ambar', 'perdida' => 'insignia-roja', 'baja' => 'insignia-gris'][$estado] ?? 'insignia-gris';
@endphp
<span {{ $attributes->merge(['class' => $c]) }}>{{ \App\Models\Herramienta::ESTADOS[$estado] ?? $estado }}</span>
