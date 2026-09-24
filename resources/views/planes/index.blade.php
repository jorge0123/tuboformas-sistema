<x-layouts.app titulo="Planes preventivos">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('planes.index')" placeholder="Plan o máquina">
        <select name="estado" class="campo w-auto">
            <option value="">Todos</option>
            <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
            <option value="pausados" @selected(request('estado') === 'pausados')>Pausados</option>
        </select>
    </x-filtros>
    @can('planes.gestionar')
    <div class="flex gap-2">
        <form method="POST" action="{{ route('planes.generar') }}">@csrf
            <button class="btn-secundario" title="Crea ahora las órdenes que el sistema genera cada mañana"><x-icono n="refrescar" clase="size-4" /> Generar órdenes de hoy</button>
        </form>
        <a href="{{ route('planes.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nuevo plan</a>
    </div>
    @endcan
</div>

<div class="tarjeta overflow-hidden">
    @if ($planes->isEmpty())
        <x-vacio icono="repetir" titulo="Sin planes preventivos" texto="Un plan es una tarea que se repite: el sistema crea la orden de trabajo sola unos días antes de cada fecha." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Plan</th><th>Frecuencia</th><th>Responsable</th><th>Próxima fecha</th><th>Orden abierta</th><th>Estado</th><th></th></tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($planes as $p)
                @php $dias = today()->diffInDays($p->proxima_fecha, false); @endphp
                <tr>
                    <td class="max-w-md">
                        <p class="truncate font-semibold text-carbon-900">{{ $p->titulo }}</p>
                        <p class="truncate text-xs text-carbon-500">{{ $p->maquina?->etiqueta() ?? 'General' }}{{ $p->especialidad ? ' · '.$p->especialidad->nombre : '' }}{{ $p->checklist ? ' · '.count($p->checklist).' pasos' : '' }}</p>
                    </td>
                    <td class="whitespace-nowrap"><span class="flex items-center gap-1.5"><x-icono n="repetir" clase="size-4 text-carbon-400" />{{ $p->frecuenciaTexto() }}</span></td>
                    <td class="whitespace-nowrap">{{ $p->responsable?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap">
                        <p class="font-semibold tabular-nums">@fecha($p->proxima_fecha)</p>
                        <p class="text-xs {{ $dias < 0 ? 'text-marca-700' : 'text-carbon-500' }}">{{ $dias === 0 ? 'Hoy' : ($dias > 0 ? "En $dias días" : 'Hace '.abs($dias).' días') }}</p>
                    </td>
                    <td>@if ($p->abiertas)<a href="{{ route('ot.index', ['q' => $p->titulo]) }}" class="insignia-azul">{{ $p->abiertas }} abierta</a>@else<span class="text-carbon-300">—</span>@endif</td>
                    <td>@if ($p->activo)<span class="insignia-verde">Activo</span>@else<span class="insignia-gris">Pausado</span>@endif</td>
                    <td class="text-right">@can('planes.gestionar')<a href="{{ route('planes.edit', $p) }}" class="btn-fantasma btn-sm"><x-icono n="lapiz" clase="size-4" /></a>@endcan</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $planes->links() }}
    @endif
</div>
</x-layouts.app>
