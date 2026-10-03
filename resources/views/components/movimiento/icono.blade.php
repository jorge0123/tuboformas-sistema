@props(['efecto'])
@php
    [$clase, $icono] = [
        'entrada' => ['bg-emerald-50 text-emerald-600', 'descargar'],
        'salida' => ['bg-marca-50 text-marca-600', 'subir'],
    ][$efecto] ?? ['bg-carbon-100 text-carbon-600', 'flechas'];
@endphp
<span {{ $attributes->merge(['class' => "grid size-9 shrink-0 place-items-center rounded-lg $clase"]) }}><x-icono :n="$icono" clase="size-[18px]" /></span>
