@props(['pct', 'detalle' => true])
{{-- Ocupación con barra y etiqueta de texto (nunca solo color). --}}
@php [$color, $texto, $insignia] = \App\Support\Colores::ocupacion($pct); @endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="h-2 min-w-16 flex-1 overflow-hidden rounded-full bg-carbon-100"><div class="h-full rounded-full" style="width: {{ $pct ?? 0 }}%; background: {{ $color }}"></div></div>
    <span class="w-10 text-right text-sm font-bold tabular-nums">{{ $pct !== null ? $pct.'%' : '—' }}</span>
    @if ($detalle)<span class="{{ $insignia }} w-[4.5rem] justify-center">{{ $texto }}</span>@endif
</div>
