@if ($paginator->hasPages())
<nav class="flex items-center justify-between gap-3 border-t border-carbon-100 px-4 py-3 text-sm" aria-label="Paginación">
    <p class="text-carbon-500">
        {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de <span class="font-semibold text-carbon-800">{{ $paginator->total() }}</span>
    </p>
    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn-secundario btn-sm opacity-40"><x-icono n="izquierda" clase="size-4" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn-secundario btn-sm" aria-label="Anterior"><x-icono n="izquierda" clase="size-4" /></a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-carbon-400">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="btn btn-sm hidden bg-carbon-900 text-white sm:inline-flex">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="btn-fantasma btn-sm hidden sm:inline-flex">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn-secundario btn-sm" aria-label="Siguiente"><x-icono n="derecha" clase="size-4" /></a>
        @else
            <span class="btn-secundario btn-sm opacity-40"><x-icono n="derecha" clase="size-4" /></span>
        @endif
    </div>
</nav>
@endif
