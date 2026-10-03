@props(['datos', 'centro' => null, 'nota' => null])
{{-- Dona con leyenda: $datos = [['etiqueta' => ..., 'valor' => n, 'color' => '#hex'], …]. Cada parte lleva su nombre y número en la leyenda. --}}
@php
    $total = collect($datos)->sum('valor');
    $acum = 0;
@endphp
@if (! $total)
    <p class="py-8 text-center text-sm text-carbon-500">Sin datos en el período.</p>
@else
<div class="flex flex-col items-center gap-5 sm:flex-row">
    <div class="relative size-36 shrink-0">
        <svg viewBox="0 0 42 42" class="size-full -rotate-90">
            <circle cx="21" cy="21" r="15.9" fill="none" stroke="#efece8" stroke-width="5"/>
            @foreach ($datos as $d)
                @continue(! $d['valor'])
                @php $pct = $d['valor'] / $total * 100; $hueco = count(array_filter($datos, fn ($x) => $x['valor'])) > 1 ? 0.8 : 0; @endphp
                <circle cx="21" cy="21" r="15.9" fill="none" stroke="{{ $d['color'] }}" stroke-width="5" class="transition-opacity hover:opacity-80"
                        stroke-dasharray="{{ max(0, $pct - $hueco) }} {{ 100 - max(0, $pct - $hueco) }}" stroke-dashoffset="{{ -$acum }}"><title>{{ $d['etiqueta'] }}: {{ $d['valor'] }} ({{ round($pct) }} %)</title></circle>
                @php $acum += $pct; @endphp
            @endforeach
        </svg>
        <div class="absolute inset-0 grid place-items-center text-center">
            <div><p class="font-display text-2xl leading-none font-extrabold tabular-nums">{{ $centro ?? $total }}</p>@if ($nota)<p class="mt-0.5 text-[11px] text-carbon-500">{{ $nota }}</p>@endif</div>
        </div>
    </div>
    <ul class="w-full min-w-0 flex-1 space-y-1.5 text-sm">
        @foreach ($datos as $d)
            @continue(! $d['valor'])
            <li class="flex items-center gap-2"><span class="size-2.5 shrink-0 rounded-sm" style="background: {{ $d['color'] }}"></span><span class="truncate text-carbon-700">{{ $d['etiqueta'] }}</span>
                <span class="ml-auto pl-3 font-semibold tabular-nums text-carbon-900">{{ $d['valor'] }}</span><span class="w-10 text-right text-xs tabular-nums text-carbon-400">{{ round($d['valor'] / $total * 100) }} %</span></li>
        @endforeach
    </ul>
</div>
@endif
