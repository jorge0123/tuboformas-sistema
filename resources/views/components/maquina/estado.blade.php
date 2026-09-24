@props(['estado'])
@php
    $c = ['operativa' => 'insignia-verde', 'en_mantenimiento' => 'insignia-azul', 'fuera_servicio' => 'insignia-roja', 'baja' => 'insignia-gris'][$estado] ?? 'insignia-gris';
    $p = ['operativa' => 'bg-emerald-500', 'en_mantenimiento' => 'bg-sky-500', 'fuera_servicio' => 'bg-marca-500', 'baja' => 'bg-carbon-400'][$estado] ?? 'bg-carbon-400';
@endphp
<span {{ $attributes->merge(['class' => $c]) }}><span class="size-1.5 rounded-full {{ $p }}"></span>{{ \App\Models\Maquina::ESTADOS[$estado] ?? $estado }}</span>
