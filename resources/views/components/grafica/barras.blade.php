@props(['datos', 'color' => 'bg-marca-600', 'sufijo' => '', 'formato' => null])
{{-- Barras horizontales: $datos = [['etiqueta' => ..., 'valor' => ..., 'nota' => ?], …] --}}
@php $max = max(1, collect($datos)->max('valor')); @endphp
<div class="space-y-3">
    @forelse ($datos as $d)
        <div>
            <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span class="truncate text-carbon-700">{{ $d['etiqueta'] }}@if (! empty($d['nota']))<span class="text-xs text-carbon-400"> · {{ $d['nota'] }}</span>@endif</span>
                <span class="shrink-0 font-semibold tabular-nums">{{ $formato === 'dinero' ? \App\Support\Formato::dinero($d['valor']) : \App\Support\Formato::numero($d['valor'], 1).$sufijo }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-carbon-100"><div class="h-full rounded-full {{ $d['color'] ?? $color }} transition-all duration-700" style="width: {{ round($d['valor'] / $max * 100, 1) }}%"></div></div>
        </div>
    @empty
        <p class="py-6 text-center text-sm text-carbon-500">Sin datos en el período.</p>
    @endforelse
</div>
