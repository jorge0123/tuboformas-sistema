<x-layouts.app titulo="Herramientas">
<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
    @foreach (\App\Models\Herramienta::ESTADOS as $k => $v)
        <a href="{{ route('herramientas.index', ['estado' => $k]) }}" class="tarjeta px-4 py-3 transition hover:shadow-md {{ request('estado') === $k ? 'ring-2 ring-marca-500' : '' }}">
            <p class="font-display text-2xl font-extrabold tabular-nums">{{ $resumen[$k] ?? 0 }}</p>
            <p class="text-xs font-medium text-carbon-500">{{ $v }}</p>
        </a>
    @endforeach
</div>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('herramientas.index')" placeholder="Código, nombre o marca">
        <select name="tecnico" class="campo w-auto">
            <option value="">Cualquier técnico</option>
            @foreach ($tecnicos as $t)<option value="{{ $t->id }}" @selected(request('tecnico') == $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        <select name="categoria" class="campo w-auto">
            <option value="">Toda categoría</option>
            @foreach ($categorias as $c)<option value="{{ $c }}" @selected(request('categoria') === $c)>{{ $c }}</option>@endforeach
        </select>
        @if (request('estado'))<input type="hidden" name="estado" value="{{ request('estado') }}">@endif
    </x-filtros>
    @can('herramientas.gestionar')<a href="{{ route('herramientas.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nueva herramienta</a>@endcan
</div>

<div class="tarjeta overflow-hidden">
    @if ($herramientas->isEmpty())
        <x-vacio icono="martillo" titulo="Sin herramientas" texto="Registra las herramientas y asígnalas a cada técnico para saber quién tiene qué." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Herramienta</th><th>Categoría</th><th>Marca</th><th>Estado</th><th>A cargo de</th><th></th></tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($herramientas as $h)
                <tr class="cursor-pointer" onclick="location='{{ route('herramientas.show', $h) }}'">
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($h->foto)
                                <img src="{{ Storage::url($h->foto) }}" alt="" class="size-10 rounded-lg object-cover ring-1 ring-carbon-200">
                            @else
                                <span class="grid size-10 place-items-center rounded-lg bg-carbon-100 text-carbon-400"><x-icono n="martillo" clase="size-5" /></span>
                            @endif
                            <div><p class="font-semibold text-carbon-900">{{ $h->nombre }}</p><p class="font-mono text-xs text-carbon-500">{{ $h->codigo }}</p></div>
                        </div>
                    </td>
                    <td>{{ $h->categoria ?? '—' }}</td>
                    <td>{{ $h->marca ?? '—' }}</td>
                    <td><x-herramienta.estado :estado="$h->estado" /></td>
                    <td>
                        @if ($h->asignadaA)
                            <span class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-carbon-100 text-[10px] font-bold">{{ $h->asignadaA->iniciales() }}</span>{{ $h->asignadaA->name }}</span>
                        @else <span class="text-carbon-400">Bodega de herramientas</span> @endif
                    </td>
                    <td class="text-right"><x-icono n="derecha" clase="size-4 text-carbon-300" /></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $herramientas->links() }}
    @endif
</div>
</x-layouts.app>
