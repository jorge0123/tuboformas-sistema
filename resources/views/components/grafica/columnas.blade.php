@props(['datos', 'series', 'alto' => 'h-44', 'sufijo' => ''])
{{--
    Columnas agrupadas por periodo, un solo eje. $series = ['clave' => ['Nombre', '#hex'], …] (máx. 2-3),
    $datos = [['etiqueta' => 'd/m', 'clave' => n, …], …]. Leyenda arriba; el valor sale al pasar el mouse.
--}}
@php $max = max(1, ...collect($series)->keys()->map(fn ($k) => collect($datos)->max($k) ?: 0)->all()); @endphp
<div>
    <div class="mb-3 flex flex-wrap gap-4 text-xs text-carbon-600">
        @foreach ($series as [$nombre, $color])<span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ $color }}"></span>{{ $nombre }}</span>@endforeach
    </div>
    <div class="flex items-end gap-1 overflow-x-auto pb-1">
        @foreach ($datos as $d)
            <div class="group flex min-w-6 flex-1 flex-col items-center gap-1">
                <div class="relative flex {{ $alto }} w-full items-end justify-center gap-0.5 rounded-md transition group-hover:bg-carbon-50">
                    @foreach ($series as $k => [$nombre, $color])
                        <div class="w-full max-w-4 rounded-t" style="height: {{ round(($d[$k] ?? 0) / $max * 100, 1) }}%; background: {{ $color }}"></div>
                    @endforeach
                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-lg bg-carbon-900 px-2.5 py-1.5 text-[11px] whitespace-nowrap text-white shadow-lg group-hover:block">
                        <p class="font-semibold">{{ $d['titulo'] ?? $d['etiqueta'] }}</p>
                        @foreach ($series as $k => [$nombre])<p>{{ $nombre }}: <b>{{ \App\Support\Formato::numero($d[$k] ?? 0, 1) }}{{ $sufijo }}</b></p>@endforeach
                    </div>
                </div>
                <span class="text-[10px] whitespace-nowrap text-carbon-400">{{ $d['etiqueta'] }}</span>
            </div>
        @endforeach
    </div>
</div>
