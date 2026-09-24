@props(['accion', 'placeholder' => 'Buscar…'])
{{-- Barra de búsqueda + filtros. Los filtros extra van en el slot. Se envía al cambiar un select. --}}
<form method="GET" action="{{ $accion }}" {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }} x-data @change="$event.target.tagName === 'SELECT' && $el.requestSubmit()">
    <div class="relative min-w-56 flex-1 sm:max-w-xs">
        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-carbon-400"><x-icono n="buscar" clase="size-4" /></span>
        <input name="q" value="{{ request('q') }}" class="campo pl-9" placeholder="{{ $placeholder }}">
    </div>
    {{ $slot }}
    @if (collect(request()->except('page'))->filter(fn ($v) => filled($v))->isNotEmpty())
        <a href="{{ $accion }}" class="btn-fantasma btn-sm"><x-icono n="x" clase="size-3.5" /> Limpiar</a>
    @endif
</form>
