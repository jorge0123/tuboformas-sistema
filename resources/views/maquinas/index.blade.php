<x-layouts.app titulo="Máquinas">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('maquinas.index')" placeholder="Código, nombre, marca o modelo">
        <select name="area" class="campo w-auto">
            <option value="">Todas las áreas</option>
            @foreach ($areas as $a)<option value="{{ $a->id }}" @selected(request('area') == $a->id)>{{ $a->nombre }}</option>@endforeach
        </select>
        <select name="estado" class="campo w-auto">
            <option value="">Activas (sin bajas)</option>
            @foreach (\App\Models\Maquina::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="criticidad" class="campo w-auto">
            <option value="">Toda criticidad</option>
            @foreach (['A', 'B', 'C'] as $c)<option value="{{ $c }}" @selected(request('criticidad') === $c)>Criticidad {{ $c }}</option>@endforeach
        </select>
    </x-filtros>
    <div class="flex gap-2">
        @can('reportes.exportar')
            <a href="{{ route('maquinas.exportar', request()->query()) }}" class="btn-secundario"><x-icono n="descargar" clase="size-4" /> Excel</a>
        @endcan
        @can('maquinas.crear')
            <a href="{{ route('maquinas.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nueva máquina</a>
        @endcan
    </div>
</div>

<div class="tarjeta overflow-hidden">
    @if ($maquinas->isEmpty())
        <x-vacio icono="maquina" titulo="No hay máquinas con esos filtros" texto="Prueba con otra búsqueda o registra una nueva máquina." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead>
                <tr>
                    <th class="w-14">No.</th><th>Máquina</th><th>Área</th><th>Marca / modelo</th>
                    <th class="hidden xl:table-cell">Observación</th><th>Estado</th><th class="text-center">OT abiertas</th><th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($maquinas as $m)
                <tr class="cursor-pointer" onclick="location='{{ route('maquinas.show', $m) }}'">
                    <td class="font-mono text-xs text-carbon-400">{{ $m->numero }}</td>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($m->foto)
                                <img src="{{ Storage::url($m->foto) }}" alt="" class="size-10 shrink-0 rounded-lg object-cover ring-1 ring-carbon-200">
                            @else
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-carbon-100 text-carbon-400"><x-icono n="maquina" clase="size-5" /></span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-carbon-900">{{ $m->nombre }}</p>
                                <p class="text-xs text-carbon-500">
                                    @if ($m->codigo)<span class="font-mono font-semibold">{{ $m->codigo }}</span>@endif
                                    <span class="ml-1 insignia-gris py-0 text-[10px]">Crit. {{ $m->criticidad }}</span>
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap">{{ $m->area?->nombre ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $m->marca ?? '—' }}@if ($m->modelo)<span class="text-carbon-400"> · {{ $m->modelo }}</span>@endif</td>
                    <td class="hidden max-w-xs truncate text-carbon-500 xl:table-cell">{{ $m->observaciones }}</td>
                    <td><x-maquina.estado :estado="$m->estado" /></td>
                    <td class="text-center">
                        @if ($m->ot_abiertas)<span class="insignia-azul">{{ $m->ot_abiertas }}</span>@else<span class="text-carbon-300">—</span>@endif
                    </td>
                    <td class="text-right"><x-icono n="derecha" clase="size-4 text-carbon-300" /></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $maquinas->links() }}
    @endif
</div>
</x-layouts.app>
