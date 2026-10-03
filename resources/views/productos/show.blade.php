<x-layouts.app :titulo="$producto->nombre">
<x-slot:migas><a href="{{ route('productos.index') }}" class="hover:text-carbon-800">Repuestos</a><x-icono n="derecha" clase="size-3" />{{ $producto->codigo }}</x-slot:migas>
@php $costos = auth()->user()->can('inventario.ver_costos'); $total = (float) $producto->existencia; @endphp

<div class="tarjeta overflow-hidden">
    <div class="flex flex-col gap-5 p-5 md:flex-row md:items-center">
        @if ($producto->foto)
            <img src="{{ Storage::url($producto->foto) }}" alt="" class="size-24 rounded-xl object-cover ring-1 ring-carbon-200">
        @else
            <span class="grid size-24 place-items-center rounded-xl bg-carbon-100 text-carbon-400"><x-icono n="paquete" clase="size-10" /></span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap gap-2">
                <span class="insignia-oscura font-mono">{{ $producto->codigo }}</span>
                <span class="insignia-gris">{{ \App\Models\Producto::TIPOS[$producto->tipo] }}</span>
                @if ($producto->categoria)<span class="insignia-gris">{{ $producto->categoria->nombre }}</span>@endif
                @if ($producto->bajoMinimo())<span class="insignia-roja"><x-icono n="alerta" clase="size-3" /> Bajo el mínimo</span>@endif
                @unless ($producto->activo)<span class="insignia-roja">Inactivo</span>@endunless
            </div>
            <h2 class="mt-2 font-display text-2xl font-extrabold">{{ $producto->nombre }}</h2>
            <p class="text-sm text-carbon-500">{{ $producto->medida ? 'Medida '.$producto->medida.' · ' : '' }}Unidad: {{ $producto->unidad->nombre }}{{ $producto->ubicacion ? ' · Ubicación '.$producto->ubicacion : '' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('movimientos.crear')<a href="{{ route('movimientos.create', ['producto' => $producto->id]) }}" class="btn-primario"><x-icono n="flechas" clase="size-4" /> Movimiento</a>@endcan
            @can('inventario.gestionar')<a href="{{ route('productos.edit', $producto) }}" class="btn-secundario"><x-icono n="lapiz" clase="size-4" /> Editar</a>@endcan
        </div>
    </div>
    <div class="grid grid-cols-2 divide-x divide-y divide-carbon-100 border-t border-carbon-100 md:grid-cols-4 md:divide-y-0">
        <div class="px-5 py-3.5"><p class="dato-etiqueta">Existencia</p><p class="font-display text-xl font-extrabold">@num($total) <span class="text-sm font-semibold text-carbon-500">{{ $producto->unidad->abreviatura }}</span></p></div>
        <div class="px-5 py-3.5"><p class="dato-etiqueta">Mínimo</p><p class="font-display text-xl font-extrabold">@num($producto->stock_minimo)</p></div>
        @if ($costos)
            <div class="px-5 py-3.5"><p class="dato-etiqueta">Costo promedio</p><p class="font-display text-xl font-extrabold">Q {{ number_format((float) $producto->costo_promedio, 4) }}</p></div>
            <div class="px-5 py-3.5"><p class="dato-etiqueta">Valor en bodega</p><p class="font-display text-xl font-extrabold">@dinero($total * (float) $producto->costo_promedio)</p></div>
        @else
            <div class="px-5 py-3.5 md:col-span-2"><p class="dato-etiqueta">Ubicación</p><p class="text-sm font-semibold">{{ $producto->ubicacion ?? '—' }}</p></div>
        @endif
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6">
        @if ($maquinas->isNotEmpty())
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Lo usan estas máquinas</h3></div>
            @foreach ($maquinas as $mp)
                <a href="{{ route('maquinas.show', $mp->maquina) }}" class="flex items-center justify-between gap-3 border-b border-carbon-50 px-5 py-3 text-sm transition last:border-0 hover:bg-carbon-50">
                    <span class="truncate font-medium">{{ $mp->maquina->etiqueta() }}</span>
                    <span class="shrink-0 text-xs text-carbon-500">@num($mp->cantidad) {{ $producto->unidad->abreviatura }}</span>
                </a>
            @endforeach
        </section>
        @endif
        @if ($producto->proveedores->isNotEmpty())
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Proveedores</h3></div>
            @foreach ($producto->proveedores as $pv)
                <a href="{{ route('proveedores.show', $pv) }}" class="flex items-center justify-between border-b border-carbon-50 px-5 py-3 text-sm transition last:border-0 hover:bg-carbon-50">
                    <span class="font-medium">{{ $pv->nombre }}</span>
                    <span class="text-xs text-carbon-500">@if ($costos && $pv->pivot->precio)@dinero($pv->pivot->precio) · @endif{{ $pv->pivot->dias_entrega !== null ? $pv->pivot->dias_entrega.' días' : '' }}</span>
                </a>
            @endforeach
        </section>
        @endif
    </div>

    <div class="space-y-6 xl:col-span-2">
        @if ($kardex)
        <section class="tarjeta overflow-hidden">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Kárdex</h3></div>
            @if ($kardex->isEmpty())
                <x-vacio icono="flechas" titulo="Sin movimientos" />
            @else
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Fecha</th><th>Movimiento</th><th class="text-right">Entrada</th><th class="text-right">Salida</th><th class="text-right">Saldo</th>@if ($costos)<th class="text-right">Costo u.</th>@endif</tr></thead>
                    <tbody class="divide-y divide-carbon-50">
                    @foreach ($kardex as $l)
                        @php $m = $l->movimiento; $sale = $m->efecto === 'salida'; @endphp
                        <tr class="{{ $m->estado === 'anulado' ? 'opacity-50' : '' }}">
                            <td class="whitespace-nowrap tabular-nums">@fecha($m->fecha)</td>
                            <td>
                                <a href="{{ route('movimientos.show', $m) }}" class="font-semibold text-carbon-900 hover:text-marca-700">{{ $m->nombreTipo() }}</a>
                                <p class="text-xs text-carbon-500">{{ $m->folio }}{{ $m->orden ? ' · '.$m->orden->folio : '' }}{{ $m->maquina ? ' · '.$m->maquina->etiqueta() : '' }}{{ $m->estado === 'anulado' ? ' · Anulado' : '' }}</p>
                            </td>
                            <td class="tabla-num font-semibold text-emerald-700">@unless ($sale)+@num($l->cantidad)@endunless</td>
                            <td class="tabla-num font-semibold text-marca-700">@if ($sale)−@num($l->cantidad)@endif</td>
                            <td class="tabla-num font-bold">@num($l->saldo)</td>
                            @if ($costos)<td class="tabla-num text-carbon-500">{{ $l->costo_unitario !== null ? 'Q '.number_format((float) $l->costo_unitario, 2) : '—' }}</td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $kardex->links() }}
            @endif
        </section>
        @endif

        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Fotos</h3></div>
            <div class="p-5"><x-archivos :modelo="$producto" tipo="producto" :puede-subir="auth()->user()->can('inventario.gestionar')" :categorias="['foto' => 'Foto', 'documento' => 'Ficha técnica']" /></div>
        </section>
    </div>
</div>
</x-layouts.app>
