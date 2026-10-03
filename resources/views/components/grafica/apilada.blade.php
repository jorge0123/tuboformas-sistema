@props(['datos'])
{{-- Una barra que reparte un total (ej. órdenes abiertas por estado), con leyenda. $datos = [['etiqueta', 'valor', 'color', 'href'?], …] --}}
@php $total = collect($datos)->sum('valor'); @endphp
@if ($total)
<div>
    <div class="flex h-3 gap-0.5 overflow-hidden rounded-full">
        @foreach ($datos as $d)
            @continue(! $d['valor'])
            <div class="h-full first:rounded-l-full last:rounded-r-full" style="width: {{ $d['valor'] / $total * 100 }}%; background: {{ $d['color'] }}" title="{{ $d['etiqueta'] }}: {{ $d['valor'] }}"></div>
        @endforeach
    </div>
    <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
        @foreach ($datos as $d)
            <li><a @if (! empty($d['href'])) href="{{ $d['href'] }}" @endif class="flex items-center gap-1.5 {{ ! empty($d['href']) ? 'hover:underline' : '' }}"><span class="size-2.5 rounded-sm" style="background: {{ $d['color'] }}"></span><span class="text-carbon-600">{{ $d['etiqueta'] }}</span> <b class="tabular-nums text-carbon-900">{{ $d['valor'] }}</b></a></li>
        @endforeach
    </ul>
</div>
@else
    <p class="py-4 text-center text-sm text-carbon-500">Sin órdenes abiertas.</p>
@endif
