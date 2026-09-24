<x-layouts.app titulo="Inventario">
<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-kpi titulo="Productos" :valor="$total" icono="cajas" />
    <x-kpi titulo="Bajo el mínimo" :valor="$bajos" icono="alerta" tono="rojo" :href="route('productos.index', ['bajo_minimo' => 1])" />
    @if ($valor !== null)
        <x-kpi titulo="Valor del inventario" :valor="\App\Support\Formato::dinero($valor)" icono="etiqueta" tono="verde" nota="Costo promedio × existencia" />
    @else
        <x-kpi titulo="Bodegas" :valor="$bodegas->count()" icono="paquete" />
    @endif
</div>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('productos.index')" placeholder="Código, nombre o medida">
        <select name="tipo" class="campo w-auto">
            <option value="">Todo tipo</option>
            @foreach (\App\Models\Producto::TIPOS as $k => $v)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="categoria" class="campo w-auto">
            <option value="">Toda categoría</option>
            @foreach ($categorias as $c)<option value="{{ $c->id }}" @selected(request('categoria') == $c->id)>{{ $c->nombre }}</option>@endforeach
        </select>
        <select name="bodega" class="campo w-auto">
            <option value="">Todas las bodegas</option>
            @foreach ($bodegas as $b)<option value="{{ $b->id }}" @selected(request('bodega') == $b->id)>{{ $b->nombre }}</option>@endforeach
        </select>
        @if (request('bajo_minimo'))<input type="hidden" name="bajo_minimo" value="1"><span class="insignia-roja">Bajo el mínimo</span>@endif
    </x-filtros>
    <div class="flex gap-2">
        @can('reportes.exportar')<a href="{{ route('productos.exportar', request()->query()) }}" class="btn-secundario"><x-icono n="descargar" clase="size-4" /> Excel</a>@endcan
        @can('inventario.gestionar')<a href="{{ route('productos.create') }}" class="btn-secundario"><x-icono n="mas" clase="size-4" /> Producto</a>@endcan
        @can('movimientos.crear')<a href="{{ route('movimientos.create') }}" class="btn-primario"><x-icono n="flechas" clase="size-4" /> Registrar movimiento</a>@endcan
    </div>
</div>

<div class="tarjeta overflow-hidden">
    @if ($productos->isEmpty())
        <x-vacio icono="cajas" titulo="Sin productos" texto="No hay productos con esos filtros." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Producto</th><th>Tipo</th>
                    @foreach ($bodegas as $b)<th class="text-right">{{ $b->codigo }}</th>@endforeach
                    <th class="text-right">Total</th><th class="text-right">Mínimo</th>
                    @can('inventario.ver_costos')<th class="text-right">Valor</th>@endcan
                </tr>
            </thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($productos as $p)
                @php $bajo = $p->bajoMinimo(); @endphp
                <tr class="cursor-pointer" onclick="location='{{ route('productos.show', $p) }}'">
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($p->foto)
                                <img src="{{ Storage::url($p->foto) }}" alt="" class="size-10 rounded-lg object-cover ring-1 ring-carbon-200">
                            @else
                                <span class="grid size-10 place-items-center rounded-lg bg-carbon-100 text-carbon-400"><x-icono n="paquete" clase="size-5" /></span>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-carbon-900">{{ $p->nombre }}</p>
                                <p class="font-mono text-xs text-carbon-500">{{ $p->codigo }}{{ $p->categoria ? ' · '.$p->categoria->nombre : '' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap text-xs">{{ \App\Models\Producto::TIPOS[$p->tipo] }}</td>
                    @foreach ($bodegas as $b)
                        @php $c = (float) ($p->existencias->firstWhere('bodega_id', $b->id)?->cantidad ?? 0); @endphp
                        <td class="tabla-num {{ $c ? '' : 'text-carbon-300' }}">@num($c)</td>
                    @endforeach
                    <td class="tabla-num"><span class="{{ $bajo ? 'insignia-roja' : 'font-bold text-carbon-900' }}">@num($p->stockTotal()) <span class="font-normal">{{ $p->unidad->abreviatura }}</span></span></td>
                    <td class="tabla-num text-carbon-500">@num($p->stock_minimo)</td>
                    @can('inventario.ver_costos')<td class="tabla-num">@dinero($p->stockTotal() * (float) $p->costo_promedio)</td>@endcan
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $productos->links() }}
    @endif
</div>
</x-layouts.app>
