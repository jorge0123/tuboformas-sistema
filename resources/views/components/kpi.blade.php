@props(['titulo', 'valor', 'icono', 'tono' => 'carbon', 'href' => null, 'nota' => null])
@php
    $tonos = [
        'carbon' => 'bg-carbon-100 text-carbon-700',
        'rojo' => 'bg-marca-50 text-marca-600',
        'ambar' => 'bg-amber-50 text-amber-600',
        'verde' => 'bg-emerald-50 text-emerald-600',
        'azul' => 'bg-sky-50 text-sky-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="tarjeta aparecer group relative flex items-start gap-4 p-4 transition sm:p-5 {{ $href ? 'hover:-translate-y-0.5 hover:shadow-md' : '' }}">
    <span class="absolute top-3 right-3 grid size-8 shrink-0 place-items-center rounded-lg sm:static sm:size-11 sm:rounded-xl {{ $tonos[$tono] }}"><x-icono :n="$icono" clase="size-4 sm:size-5" /></span>
    <div class="min-w-0 flex-1 pr-9 sm:pr-0">
        <p class="text-[13px] leading-tight font-medium text-carbon-500 sm:text-sm">{{ $titulo }}</p>
        <p class="mt-0.5 truncate font-display text-2xl font-extrabold tracking-tight whitespace-nowrap text-carbon-900 tabular-nums 2xl:text-3xl">{{ $valor }}</p>
        @if ($nota)<p class="mt-0.5 hidden text-xs text-carbon-500 sm:block">{{ $nota }}</p>@endif
    </div>
</{{ $tag }}>
