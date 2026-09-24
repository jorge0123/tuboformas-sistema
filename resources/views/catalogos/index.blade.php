<x-layouts.app titulo="Catálogos">
<div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <nav class="tarjeta h-fit p-2">
        @foreach ($catalogos as $k => [$t])
            <a href="{{ route('catalogos.index', $k) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition {{ $actual === $k ? 'bg-carbon-900 text-white' : 'text-carbon-700 hover:bg-carbon-100' }}">
                {{ $t }} <x-icono n="derecha" clase="size-4 opacity-50" />
            </a>
        @endforeach
    </nav>

    <section class="tarjeta lg:col-span-3">
        <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">{{ $titulo }}</h3><p class="text-xs text-carbon-500">{{ $ayuda }}</p></div></div>
        <form method="POST" action="{{ route('catalogos.store', $actual) }}" class="flex flex-wrap gap-2 border-b border-carbon-100 bg-carbon-50/50 p-4">
            @csrf
            @if ($actual === 'bodegas')<input name="codigo" required class="campo w-28" placeholder="Código">@endif
            <input name="nombre" required class="campo min-w-48 flex-1" placeholder="Nuevo…">
            @if ($actual === 'unidades')<input name="abreviatura" required class="campo w-28" placeholder="Abrev.">@endif
            @if ($actual === 'bodegas')<input name="descripcion" class="campo min-w-48 flex-1" placeholder="Descripción">@endif
            <button class="btn-oscuro"><x-icono n="mas" clase="size-4" /> Agregar</button>
        </form>
        <div class="divide-y divide-carbon-100">
            @foreach ($items as $it)
                <form method="POST" action="{{ route('catalogos.update', [$actual, $it->id]) }}" class="flex flex-wrap items-center gap-2 px-4 py-2.5 {{ $it->activo ? '' : 'bg-carbon-50/60' }}" x-data="{ cambiado: false }" @input="cambiado = true">
                    @csrf @method('PUT')
                    @if ($actual === 'bodegas')<input name="codigo" value="{{ $it->codigo }}" required class="campo w-28 py-1.5 font-mono">@endif
                    <input name="nombre" value="{{ $it->nombre }}" required class="campo min-w-48 flex-1 py-1.5 {{ $it->activo ? '' : 'text-carbon-400' }}">
                    @if ($actual === 'unidades')<input name="abreviatura" value="{{ $it->abreviatura }}" required class="campo w-28 py-1.5">@endif
                    @if ($actual === 'bodegas')<input name="descripcion" value="{{ $it->descripcion }}" class="campo min-w-48 flex-1 py-1.5">@endif
                    <label class="flex items-center gap-1.5 text-xs text-carbon-600"><input type="checkbox" name="activo" value="1" class="check" @checked($it->activo) @change="cambiado = true"> Activo</label>
                    <button class="btn-secundario btn-sm" x-show="cambiado" x-cloak x-transition><x-icono n="check" clase="size-4" /> Guardar</button>
                </form>
            @endforeach
        </div>
    </section>
</div>
</x-layouts.app>
