@props(['prioridad'])
@php
    $c = ['baja' => 'bg-carbon-300', 'media' => 'bg-sky-500', 'alta' => 'bg-amber-500', 'critica' => 'bg-marca-600'][$prioridad] ?? 'bg-carbon-300';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs font-semibold text-carbon-700']) }}>
    <span class="size-2 rounded-full {{ $c }}"></span>{{ \App\Models\OrdenTrabajo::PRIORIDADES[$prioridad] ?? $prioridad }}
</span>
