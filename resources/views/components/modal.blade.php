@props(['nombre', 'titulo', 'ancho' => 'max-w-lg'])
{{-- Ventana modal. Abrir con: $dispatch('abrir-modal', '{{ $nombre }}') --}}
<div x-data="{ abierto: false }" x-show="abierto" x-cloak
     @abrir-modal.window="if ($event.detail === '{{ $nombre }}') { abierto = true; $nextTick(() => $el.querySelector('[autofocus], input:not([type=hidden]), select, textarea')?.focus()) }"
     @cerrar-modal.window="abierto = false" @keydown.escape.window="abierto = false"
     class="fixed inset-0 z-[65] flex items-end justify-center p-0 sm:items-center sm:p-4">
    <div x-show="abierto" x-transition.opacity.duration.200ms class="absolute inset-0 bg-carbon-950/50 backdrop-blur-[2px]" @click="abierto = false"></div>
    <div x-show="abierto"
         x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-2 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
         class="relative flex max-h-[92vh] w-full {{ $ancho }} flex-col rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl" role="dialog" aria-modal="true">
        <div class="flex items-center justify-between gap-4 border-b border-carbon-100 px-6 py-4">
            <h3 class="font-display text-lg font-bold text-carbon-900">{{ $titulo }}</h3>
            <button type="button" class="rounded-md p-1 text-carbon-400 transition hover:bg-carbon-100 hover:text-carbon-700" @click="abierto = false" aria-label="Cerrar">
                <x-icono n="x" />
            </button>
        </div>
        <div class="overflow-y-auto px-6 py-5">{{ $slot }}</div>
    </div>
</div>
