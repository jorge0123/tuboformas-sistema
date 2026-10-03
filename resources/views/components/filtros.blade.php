@props(['accion', 'placeholder' => 'Buscar…'])
{{--
    Barra de búsqueda + filtros. Busca mientras se escribe (resources/js/vivo.js); los filtros extra van en el slot.
    En celular los filtros se pliegan detrás de un botón "Filtros" que muestra cuántos hay activos.
--}}
@php $activos = collect(request()->except(['q', 'page']))->filter(fn ($v) => filled($v))->count(); @endphp
<form method="GET" action="{{ $accion }}" data-filtro-vivo role="search" x-data="{ mas: false }"
      {{ $attributes->merge(['class' => 'group flex flex-wrap items-center gap-2']) }}>
    <div class="flex w-full items-center gap-2 sm:contents">
        <div class="relative min-w-0 flex-1 sm:max-w-xs sm:min-w-56">
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-carbon-400">
                <x-icono n="buscar" clase="size-4 group-aria-busy:hidden" />
                <svg class="hidden size-4 animate-spin text-marca-600 group-aria-busy:block" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-20"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
            </span>
            <input name="q" type="search" value="{{ request('q') }}" class="campo pl-9 [&::-webkit-search-cancel-button]:hidden" placeholder="{{ $placeholder }}" autocomplete="off" enterkeyhint="search" aria-label="Buscar">
        </div>
        @if ($slot->isNotEmpty())
            <button type="button" class="btn-secundario relative h-[2.625rem] shrink-0 sm:hidden" @click="mas = !mas" :aria-expanded="mas.toString()"
                    :class="mas && 'ring-carbon-400 bg-carbon-50'">
                <x-icono n="filtro" clase="size-4" /> Filtros
                @if ($activos)<span class="grid size-5 place-items-center rounded-full bg-marca-600 text-[11px] font-bold text-white">{{ $activos }}</span>@endif
            </button>
        @endif
    </div>
    <div class="filtros-extra hidden w-full flex-wrap items-center gap-2 sm:contents" :class="mas && 'max-sm:!flex'">
        {{ $slot }}
        @if ($activos || request()->filled('q'))
            <a href="{{ $accion }}" class="btn-fantasma btn-sm"><x-icono n="x" clase="size-3.5" /> Limpiar</a>
        @endif
    </div>
</form>
