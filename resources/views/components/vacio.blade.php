@props(['icono' => 'lista', 'titulo' => 'Sin registros', 'texto' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-carbon-100 text-carbon-400"><x-icono :n="$icono" clase="size-7" /></span>
    <p class="mt-4 font-display text-base font-bold text-carbon-800">{{ $titulo }}</p>
    @if ($texto)<p class="mt-1 max-w-sm text-sm text-carbon-500">{{ $texto }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
