<x-layouts.app titulo="Órdenes de trabajo">
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="inline-flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-carbon-200">
        <a href="{{ route('ot.index', request()->query()) }}" class="flex items-center gap-1.5 rounded-md bg-carbon-900 px-3 py-1.5 text-sm font-semibold text-white"><x-icono n="lista" clase="size-4" /> Lista</a>
        <a href="{{ route('ot.kanban', request()->except('estado', 'page')) }}" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold text-carbon-600 hover:text-carbon-900"><x-icono n="kanban" clase="size-4" /> Kanban</a>
        <a href="{{ route('calendario') }}" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold text-carbon-600 hover:text-carbon-900"><x-icono n="calendario" clase="size-4" /> Calendario</a>
    </div>
    <div class="flex gap-2">
        @can('reportes.exportar')<a href="{{ route('ot.exportar', request()->query()) }}" class="btn-secundario"><x-icono n="descargar" clase="size-4" /> Excel</a>@endcan
        @can('ot.crear')<a href="{{ route('ot.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nueva orden</a>@endcan
    </div>
</div>
<div class="mb-5">@include('ot._filtros', ['accion' => route('ot.index'), 'conEstado' => true])</div>

<div class="tarjeta overflow-hidden">
    @if ($ordenes->isEmpty())
        <x-vacio icono="portapapeles" titulo="No hay órdenes con esos filtros" texto="Cambia los filtros o crea una nueva orden de trabajo." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead>
                <tr><th>Orden</th><th>Responsable</th><th>Prioridad</th><th>Vence</th><th>Situación</th><th>Estado</th><th class="w-36">Avance</th><th class="hidden 2xl:table-cell">Comentario</th></tr>
            </thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($ordenes as $ot)
                <tr class="cursor-pointer" onclick="location='{{ route('ot.show', $ot) }}'">
                    <td class="max-w-md">
                        <p class="truncate font-semibold text-carbon-900">{{ $ot->titulo }}</p>
                        <p class="truncate text-xs text-carbon-500"><span class="font-mono font-semibold">{{ $ot->folio }}</span> · {{ $ot->maquina?->etiqueta() ?? 'General' }} · {{ \App\Models\OrdenTrabajo::TIPOS[$ot->tipo] }}{{ $ot->especialidad ? ' · '.$ot->especialidad->nombre : '' }}</p>
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($ot->responsable)
                            <span class="flex items-center gap-2"><span class="grid size-7 place-items-center rounded-full bg-carbon-100 text-[10px] font-bold text-carbon-700">{{ $ot->responsable->iniciales() }}</span>{{ $ot->responsable->name }}</span>
                        @else
                            <span class="insignia-ambar">Sin asignar</span>
                        @endif
                    </td>
                    <td><x-ot.prioridad :prioridad="$ot->prioridad" /></td>
                    <td class="whitespace-nowrap tabular-nums">@fecha($ot->fecha_vencimiento)</td>
                    <td><x-ot.situacion :ot="$ot" /></td>
                    <td><x-ot.estado :estado="$ot->estado" /></td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="barra flex-1"><span style="width: {{ $ot->progreso }}%" class="{{ $ot->estado === 'completada' ? '!bg-emerald-500' : '' }}"></span></div>
                            <span class="w-9 text-right text-xs font-semibold tabular-nums">{{ $ot->progreso }}%</span>
                        </div>
                    </td>
                    <td class="hidden max-w-xs truncate text-carbon-500 2xl:table-cell">{{ $ot->motivo_espera }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $ordenes->links() }}
    @endif
</div>
</x-layouts.app>
