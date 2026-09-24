<x-layouts.app titulo="Movimientos de bodega">
@if ($pendientes && auth()->user()->can('movimientos.aprobar'))
    <a href="{{ route('movimientos.index', ['estado' => 'pendiente']) }}" class="mb-5 flex items-center gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 transition hover:bg-amber-100">
        <x-icono n="alerta" clase="size-5 shrink-0" /><span><b>{{ $pendientes }}</b> {{ $pendientes === 1 ? 'ajuste espera' : 'ajustes esperan' }} tu aprobación.</span><x-icono n="derecha" clase="ml-auto size-4" />
    </a>
@endif
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('movimientos.index')" placeholder="Folio, documento, producto…">
        <select name="tipo" class="campo w-auto">
            <option value="">Todo tipo</option>
            @foreach (\App\Models\Movimiento::TIPOS as $k => $t)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $t['nombre'] }}</option>@endforeach
        </select>
        <select name="estado" class="campo w-auto">
            <option value="">Todo estado</option>
            @foreach (\App\Models\Movimiento::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="bodega" class="campo w-auto">
            <option value="">Todas las bodegas</option>
            @foreach ($bodegas as $b)<option value="{{ $b->id }}" @selected(request('bodega') == $b->id)>{{ $b->nombre }}</option>@endforeach
        </select>
        <input type="date" name="desde" value="{{ request('desde') }}" class="campo w-auto" onchange="this.form.requestSubmit()" title="Desde">
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="campo w-auto" onchange="this.form.requestSubmit()" title="Hasta">
    </x-filtros>
    <div class="flex gap-2">
        @can('reportes.exportar')<a href="{{ route('movimientos.exportar', request()->query()) }}" class="btn-secundario"><x-icono n="descargar" clase="size-4" /> Excel</a>@endcan
        @can('movimientos.crear')<a href="{{ route('movimientos.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Registrar movimiento</a>@endcan
    </div>
</div>

<div class="tarjeta overflow-hidden">
    @if ($movimientos->isEmpty())
        <x-vacio icono="flechas" titulo="Sin movimientos" texto="No hay movimientos con esos filtros." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Movimiento</th><th>Fecha</th><th>Bodega</th><th>Detalle</th><th class="text-center">Productos</th><th>Registró</th><th>Estado</th></tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($movimientos as $m)
                <tr class="cursor-pointer" onclick="location='{{ route('movimientos.show', $m) }}'">
                    <td>
                        <div class="flex items-center gap-3">
                            <x-movimiento.icono :efecto="$m->efecto" />
                            <div><p class="font-semibold text-carbon-900">{{ $m->nombreTipo() }}</p><p class="font-mono text-xs text-carbon-500">{{ $m->folio }}</p></div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap tabular-nums">@fecha($m->fecha)</td>
                    <td class="whitespace-nowrap text-xs">
                        @if ($m->efecto === 'traslado'){{ $m->bodegaOrigen?->codigo }} <x-icono n="flecha-derecha" clase="inline size-3" /> {{ $m->bodegaDestino?->codigo }}
                        @else{{ ($m->bodegaDestino ?? $m->bodegaOrigen)?->nombre }}@endif
                    </td>
                    <td class="max-w-xs truncate text-carbon-500">{{ $m->proveedor?->nombre ?? $m->referencia ?? $m->documento ?? $m->notas }}</td>
                    <td class="text-center">{{ $m->lineas_count }}</td>
                    <td class="whitespace-nowrap">{{ $m->user->name }}</td>
                    <td><x-movimiento.estado :estado="$m->estado" /></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $movimientos->links() }}
    @endif
</div>
</x-layouts.app>
