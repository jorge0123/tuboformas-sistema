@props(['modelo', 'tipo', 'puedeSubir' => false, 'categorias' => null, 'titulo' => 'Documentos y fotos'])
{{-- Galería de adjuntos + subida con cámara del celular (las fotos se comprimen antes de subir). --}}
@php
    $archivos = $modelo->archivos;
    $imagenes = $archivos->filter->esImagen();
    $docs = $archivos->reject->esImagen();
@endphp
<div x-data="{ visor: null }">
    @if ($puedeSubir)
    <div class="mb-4 flex flex-wrap items-center gap-2" x-data="subidor({ url: '{{ route('archivos.store') }}', tipo: '{{ $tipo }}', id: {{ $modelo->id }} })">
        @if ($categorias)
            <select x-ref="categoria" class="campo w-auto">
                @foreach ($categorias as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
        @endif
        <label class="btn-oscuro cursor-pointer" :class="subiendo && 'pointer-events-none opacity-60'">
            <x-icono n="camara" clase="size-4" /> Tomar foto
            <input type="file" accept="image/*" capture="environment" class="sr-only" @change="subir">
        </label>
        <label class="btn-secundario cursor-pointer" :class="subiendo && 'pointer-events-none opacity-60'">
            <x-icono n="clip" clase="size-4" /> Adjuntar archivos
            <input type="file" multiple accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.dwg" class="sr-only" @change="subir">
        </label>
        <span x-show="subiendo" x-cloak class="flex items-center gap-2 text-sm text-carbon-500">
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-20"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            Subiendo <span x-text="progreso"></span>…
        </span>
    </div>
    @endif

    @if ($archivos->isEmpty())
        <x-vacio icono="imagen" titulo="Sin archivos" texto="Fotos, manuales y diagramas aparecerán aquí." class="rounded-xl border border-dashed border-carbon-200 py-10" />
    @else
        @if ($imagenes->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ($imagenes as $a)
                <div class="group relative aspect-square overflow-hidden rounded-xl bg-carbon-100 ring-1 ring-carbon-200">
                    <button type="button" class="size-full" @click="visor = @js(['url' => $a->url(), 'nombre' => $a->nombre])">
                        <img src="{{ $a->url() }}" alt="{{ $a->nombre }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
                    </button>
                    <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-2 pt-6 pb-1.5 text-[11px] font-medium text-white">
                        {{ \App\Models\Archivo::CATEGORIAS[$a->categoria] ?? $a->categoria }} · {{ $a->created_at->format('d/m/Y') }}
                    </span>
                    @if ($puedeSubir)
                    <form method="POST" action="{{ route('archivos.destroy', $a) }}" data-confirmar="La foto se eliminará definitivamente." data-titulo="Eliminar foto" data-boton="Eliminar" data-peligro
                          class="absolute top-1.5 right-1.5 opacity-0 transition group-hover:opacity-100">
                        @csrf @method('DELETE')
                        <button class="grid size-7 place-items-center rounded-lg bg-white/90 text-marca-700 shadow hover:bg-white" aria-label="Eliminar"><x-icono n="basura" clase="size-3.5" /></button>
                    </form>
                    @endif
                </div>
            @endforeach
        </div>
        @endif
        @if ($docs->isNotEmpty())
        <ul class="mt-3 divide-y divide-carbon-100 rounded-xl ring-1 ring-carbon-200">
            @foreach ($docs as $a)
                <li class="flex items-center gap-3 px-4 py-3">
                    <span class="grid size-9 place-items-center rounded-lg bg-marca-50 text-marca-600"><x-icono n="archivo" clase="size-[18px]" /></span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ $a->url() }}" target="_blank" class="block truncate text-sm font-semibold text-carbon-900 hover:text-marca-700">{{ $a->nombre }}</a>
                        <p class="text-xs text-carbon-500">{{ \App\Models\Archivo::CATEGORIAS[$a->categoria] ?? $a->categoria }} · {{ \App\Support\Formato::bytes($a->tamano) }} · {{ $a->user?->name }} · {{ $a->created_at->format('d/m/Y') }}</p>
                    </div>
                    <a href="{{ $a->url() }}" target="_blank" class="btn-fantasma btn-sm"><x-icono n="externo" clase="size-4" /></a>
                    @if ($puedeSubir)
                    <form method="POST" action="{{ route('archivos.destroy', $a) }}" data-confirmar="El archivo se eliminará definitivamente." data-titulo="Eliminar archivo" data-boton="Eliminar" data-peligro>
                        @csrf @method('DELETE')
                        <button class="btn-fantasma btn-sm text-marca-700" aria-label="Eliminar"><x-icono n="basura" clase="size-4" /></button>
                    </form>
                    @endif
                </li>
            @endforeach
        </ul>
        @endif
    @endif

    {{-- Visor de fotos --}}
    <div x-show="visor" x-cloak x-transition.opacity class="fixed inset-0 z-[75] grid place-items-center bg-carbon-950/90 p-4" @click.self="visor = null" @keydown.escape.window="visor = null">
        <button class="absolute top-4 right-4 grid size-10 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" @click="visor = null" aria-label="Cerrar"><x-icono n="x" /></button>
        <figure class="max-h-full max-w-5xl" x-show="visor" x-transition.scale.95>
            <img :src="visor?.url" :alt="visor?.nombre" class="max-h-[85vh] rounded-lg object-contain shadow-2xl">
            <figcaption class="mt-3 text-center text-sm text-carbon-300" x-text="visor?.nombre"></figcaption>
        </figure>
    </div>
</div>
