<x-layouts.app :titulo="$producto->nombre">
<x-slot:migas><a href="{{ route('productos.index') }}" class="hover:text-carbon-800">Inventario</a><x-icono n="derecha" clase="size-3" />{{ $producto->codigo }}</x-slot:migas>
@php $costos = auth()->user()->can('inventario.ver_costos'); $total = $producto->stockTotal(); @endphp

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
        <div class="px-5 py-3.5"><p class="dato-etiqueta">Existencia total</p><p class="font-display text-xl font-extrabold">@num($total) <span class="text-sm font-semibold text-carbon-500">{{ $producto->unidad->abreviatura }}</span></p></div>
        <div class="px-5 py-3.5"><p class="dato-etiqueta">Mínimo</p><p class="font-display text-xl font-extrabold">@num($producto->stock_minimo)</p></div>
        @if ($costos)
            <div class="px-5 py-3.5"><p class="dato-etiqueta">Costo promedio</p><p class="font-display text-xl font-extrabold">Q {{ number_format((float) $producto->costo_promedio, 4) }}</p></div>
            <div class="px-5 py-3.5"><p class="dato-etiqueta">Valor en inventario</p><p class="font-display text-xl font-extrabold">@dinero($total * (float) $producto->costo_promedio)</p></div>
        @else
            <div class="px-5 py-3.5 md:col-span-2"><p class="dato-etiqueta">Presentaciones</p><p class="text-sm font-semibold">{{ $producto->presentaciones->map(fn ($p) => $p->nombre.' = '.\App\Support\Formato::numero($p->factor))->join(' · ') ?: '—' }}</p></div>
        @endif
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Existencia por bodega</h3></div>
            @foreach ($bodegas as $b)
                @php $c = (float) ($producto->existencias->firstWhere('bodega_id', $b->id)?->cantidad ?? 0); @endphp
                <div class="flex items-center justify-between border-b border-carbon-50 px-5 py-3 last:border-0">
                    <span class="text-sm"><span class="font-mono text-xs font-bold text-carbon-400">{{ $b->codigo }}</span> {{ $b->nombre }}</span>
                    <span class="font-semibold tabular-nums {{ $c ? '' : 'text-carbon-300' }}">@num($c)</span>
                </div>
            @endforeach
        </section>
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Presentaciones</h3></div>
            @forelse ($producto->presentaciones as $pr)
                <div class="flex items-center justify-between border-b border-carbon-50 px-5 py-3 text-sm last:border-0">
                    <span class="font-medium">{{ $pr->nombre }}</span>
                    <span class="text-carbon-600">= @num($pr->factor) {{ $producto->unidad->abreviatura }}</span>
                </div>
            @empty
                <p class="px-5 py-5 text-sm text-carbon-500">Se cuenta solo en {{ mb_strtolower($producto->unidad->nombre) }}.</p>
            @endforelse
        </section>
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
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Kárdex</h3>
                <form method="GET" x-data @change="$el.requestSubmit()">
                    <select name="bodega" class="campo w-auto py-1.5 text-xs">
                        <option value="">Todas las bodegas</option>
                        @foreach ($bodegas as $b)<option value="{{ $b->id }}" @selected(request('bodega') == $b->id)>{{ $b->nombre }}</option>@endforeach
                    </select>
                </form>
            </div>
            @if ($kardex->isEmpty())
                <x-vacio icono="flechas" titulo="Sin movimientos" />
            @else
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Fecha</th><th>Movimiento</th><th>Bodega</th><th class="text-right">Entrada</th><th class="text-right">Salida</th><th class="text-right">Saldo</th>@if ($costos)<th class="text-right">Costo u.</th>@endif</tr></thead>
                    <tbody class="divide-y divide-carbon-50">
                    @foreach ($kardex as $l)
                        @php
                            $m = $l->movimiento;
                            $filas = [];
                            if (in_array($m->efecto, ['salida', 'traslado'])) $filas[] = ['bodega' => $m->bodegaOrigen, 'sale' => $l->cantidad_base, 'entra' => null, 'saldo' => $l->saldo_origen];
                            if (in_array($m->efecto, ['entrada', 'traslado'])) $filas[] = ['bodega' => $m->bodegaDestino, 'sale' => null, 'entra' => $l->cantidad_base, 'saldo' => $l->saldo_destino];
                        @endphp
                        @foreach ($filas as $f)
                            @continue(request('bodega') && $f['bodega']?->id != request('bodega'))
                            <tr class="{{ $m->estado === 'anulado' ? 'opacity-50' : '' }}">
                                <td class="whitespace-nowrap tabular-nums">@fecha($m->fecha)</td>
                                <td>
                                    <a href="{{ route('movimientos.show', $m) }}" class="font-semibold text-carbon-900 hover:text-marca-700">{{ $m->nombreTipo() }}</a>
                                    <p class="text-xs text-carbon-500">{{ $m->folio }}{{ $l->presentacion ? ' · '.\App\Support\Formato::numero($l->cantidad).' '.$l->presentacion->nombre : '' }}{{ $m->estado === 'anulado' ? ' · Anulado' : '' }}</p>
                                </td>
                                <td class="whitespace-nowrap text-xs">{{ $f['bodega']?->codigo }}</td>
                                <td class="tabla-num font-semibold text-emerald-700">@if ($f['entra'])+@num($f['entra'])@endif</td>
                                <td class="tabla-num font-semibold text-marca-700">@if ($f['sale'])−@num($f['sale'])@endif</td>
                                <td class="tabla-num font-bold">@num($f['saldo'])</td>
                                @if ($costos)<td class="tabla-num text-carbon-500">{{ $l->costo_unitario !== null ? 'Q '.number_format((float) $l->costo_unitario, 2) : '—' }}</td>@endif
                            </tr>
                        @endforeach
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
