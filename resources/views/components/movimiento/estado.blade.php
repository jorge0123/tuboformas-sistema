@props(['estado'])
@php
    $c = ['pendiente' => 'insignia-ambar', 'confirmado' => 'insignia-verde', 'rechazado' => 'insignia-roja', 'anulado' => 'insignia-gris'][$estado] ?? 'insignia-gris';
    $t = ['pendiente' => 'Por aprobar', 'confirmado' => 'Confirmado', 'rechazado' => 'Rechazado', 'anulado' => 'Anulado'][$estado] ?? $estado;
@endphp
<span {{ $attributes->merge(['class' => $c]) }}>{{ $t }}</span>
