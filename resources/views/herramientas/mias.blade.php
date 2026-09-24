<x-layouts.app titulo="Mis herramientas">
<p class="mb-5 text-sm text-carbon-600">Herramientas que están a tu cargo. Si alguna se daña o se pierde, avisa al coordinador para que registre la devolución.</p>
@if ($herramientas->isEmpty())
    <div class="tarjeta"><x-vacio icono="caja-herr" titulo="No tienes herramientas asignadas" /></div>
@else
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($herramientas as $h)
        <div class="tarjeta flex items-center gap-4 p-4">
            @if ($h->foto)
                <img src="{{ Storage::url($h->foto) }}" alt="" class="size-16 rounded-xl object-cover ring-1 ring-carbon-200">
            @else
                <span class="grid size-16 place-items-center rounded-xl bg-carbon-100 text-carbon-400"><x-icono n="martillo" clase="size-7" /></span>
            @endif
            <div class="min-w-0">
                <p class="font-mono text-xs font-semibold text-carbon-400">{{ $h->codigo }}</p>
                <p class="truncate font-semibold">{{ $h->nombre }}</p>
                <p class="text-xs text-carbon-500">{{ $h->marca }} · desde {{ $h->asignaciones->first()?->entregado_at?->format('d/m/Y') }}</p>
            </div>
        </div>
    @endforeach
</div>
@endif
</x-layouts.app>
