<x-layouts.app :titulo="$m->folio">
<x-slot:migas><a href="{{ route('movimientos.index') }}" class="hover:text-carbon-800">Movimientos</a><x-icono n="derecha" clase="size-3" />{{ $m->folio }}</x-slot:migas>
@php $costos = auth()->user()->can('inventario.ver_costos'); @endphp

<div class="tarjeta p-5">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-4">
            <x-movimiento.icono :efecto="$m->efecto" class="size-12" />
            <div>
                <div class="flex flex-wrap items-center gap-2"><span class="font-mono text-sm font-bold text-carbon-400">{{ $m->folio }}</span><x-movimiento.estado :estado="$m->estado" /></div>
                <h2 class="font-display text-2xl font-extrabold">{{ $m->nombreTipo() }}</h2>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($m->estado === 'pendiente')
                @can('movimientos.aprobar')
                    <form method="POST" action="{{ route('movimientos.aprobar', $m) }}" data-confirmar="La existencia se actualizará con este ajuste." data-titulo="Aprobar ajuste" data-boton="Aprobar">@csrf
                        <button class="btn-exito"><x-icono n="check" clase="size-4" /> Aprobar</button>
                    </form>
                    <button class="btn-peligro" @click="$dispatch('abrir-modal', 'rechazar')"><x-icono n="x" clase="size-4" /> Rechazar</button>
                @endcan
            @elseif ($m->estado === 'confirmado' && $m->tipo !== 'reverso')
                @can('movimientos.anular')
                    <button class="btn-peligro" @click="$dispatch('abrir-modal', 'anular')"><x-icono n="deshacer" clase="size-4" /> Anular</button>
                @endcan
            @endif
            <button class="btn-secundario" onclick="window.print()"><x-icono n="imprimir" clase="size-4" /> Imprimir</button>
        </div>
    </div>

    <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-carbon-100 pt-5 sm:grid-cols-4">
        <div><dt class="dato-etiqueta">Fecha</dt><dd class="dato-valor">@fecha($m->fecha)</dd></div>
        @if ($m->bodegaOrigen)<div><dt class="dato-etiqueta">Sale de</dt><dd class="dato-valor">{{ $m->bodegaOrigen->nombre }}</dd></div>@endif
        @if ($m->bodegaDestino)<div><dt class="dato-etiqueta">Entra a</dt><dd class="dato-valor">{{ $m->bodegaDestino->nombre }}</dd></div>@endif
        @if ($m->proveedor)<div><dt class="dato-etiqueta">Proveedor</dt><dd class="dato-valor"><a href="{{ route('proveedores.show', $m->proveedor) }}" class="hover:text-marca-700">{{ $m->proveedor->nombre }}</a></dd></div>@endif
        @if ($m->maquina)<div><dt class="dato-etiqueta">Máquina</dt><dd class="dato-valor"><a href="{{ route('maquinas.show', $m->maquina) }}" class="hover:text-marca-700">{{ $m->maquina->etiqueta() }}</a></dd></div>@endif
        @if ($m->orden)<div><dt class="dato-etiqueta">Orden de trabajo</dt><dd class="dato-valor"><a href="{{ route('ot.show', $m->orden) }}" class="hover:text-marca-700">{{ $m->orden->folio }}</a></dd></div>@endif
        @if ($m->documento)<div><dt class="dato-etiqueta">Documento</dt><dd class="dato-valor">{{ $m->documento }}</dd></div>@endif
        @if ($m->referencia)<div><dt class="dato-etiqueta">Referencia</dt><dd class="dato-valor">{{ $m->referencia }}</dd></div>@endif
        <div><dt class="dato-etiqueta">Registró</dt><dd class="dato-valor">{{ $m->user->name }} · {{ $m->created_at->format('d/m/Y H:i') }}</dd></div>
        @if ($m->aprobador && ($m->estado === 'rechazado' || $m->aprobado_por !== $m->user_id))
            <div><dt class="dato-etiqueta">{{ $m->estado === 'rechazado' ? 'Rechazó' : 'Aprobó' }}</dt><dd class="dato-valor">{{ $m->aprobador->name }} · {{ $m->aprobado_at?->format('d/m/Y H:i') }}</dd></div>
        @endif
    </dl>
    @if ($m->notas)<p class="mt-4 rounded-lg bg-carbon-50 px-4 py-3 text-sm text-carbon-700">{{ $m->notas }}</p>@endif
    @if ($m->motivo_rechazo)<p class="mt-4 rounded-lg bg-marca-50 px-4 py-3 text-sm text-marca-800 ring-1 ring-marca-200">Rechazado: {{ $m->motivo_rechazo }}</p>@endif
    @if ($m->reverso)<a href="{{ route('movimientos.show', $m->reverso) }}" class="mt-4 flex items-center gap-2 rounded-lg bg-carbon-100 px-4 py-3 text-sm hover:bg-carbon-200"><x-icono n="deshacer" clase="size-4" /> Anulado con el reverso <b>{{ $m->reverso->folio }}</b></a>@endif
    @if ($m->origen)<a href="{{ route('movimientos.show', $m->origen) }}" class="mt-4 flex items-center gap-2 rounded-lg bg-carbon-100 px-4 py-3 text-sm hover:bg-carbon-200"><x-icono n="deshacer" clase="size-4" /> Reverso de <b>{{ $m->origen->folio }}</b></a>@endif
</div>

<section class="tarjeta mt-6 overflow-hidden">
    <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Productos</h3></div>
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">En unidad base</th>@if ($costos)<th class="text-right">Costo u.</th><th class="text-right">Total</th>@endif</tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($m->lineas as $l)
                <tr>
                    <td><a href="{{ route('productos.show', $l->producto) }}" class="font-semibold text-carbon-900 hover:text-marca-700">{{ $l->producto->nombre }}</a><p class="font-mono text-xs text-carbon-500">{{ $l->producto->codigo }}</p></td>
                    <td class="tabla-num">@num($l->cantidad) {{ $l->presentacion?->nombre ?? $l->producto->unidad->abreviatura }}</td>
                    <td class="tabla-num font-semibold">@num($l->cantidad_base) {{ $l->producto->unidad->abreviatura }}</td>
                    @if ($costos)
                        <td class="tabla-num">{{ $l->costo_unitario !== null ? 'Q '.number_format((float) $l->costo_unitario, 4) : '—' }}</td>
                        <td class="tabla-num font-semibold">@dinero((float) $l->cantidad_base * (float) $l->costo_unitario)</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
            @if ($costos)
            <tfoot><tr class="bg-carbon-50"><td colspan="4" class="px-4 py-3 text-right text-sm font-semibold">Total</td><td class="px-4 py-3 text-right font-display font-extrabold">@dinero($m->lineas->sum(fn ($l) => (float) $l->cantidad_base * (float) $l->costo_unitario))</td></tr></tfoot>
            @endif
        </table>
    </div>
</section>

<section class="tarjeta mt-6">
    <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Evidencia</h3></div>
    <div class="p-5"><x-archivos :modelo="$m" tipo="movimiento" :puede-subir="auth()->user()->can('movimientos.crear') && $m->estado !== 'anulado'" :categorias="['evidencia' => 'Evidencia', 'documento' => 'Documento']" /></div>
</section>

@can('movimientos.aprobar')
<x-modal nombre="rechazar" titulo="Rechazar ajuste">
    <form method="POST" action="{{ route('movimientos.rechazar', $m) }}" class="space-y-4">@csrf
        <div><label class="etiqueta">Motivo</label><textarea name="motivo" rows="3" required class="campo" placeholder="Se avisará a quien lo registró"></textarea></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secundario" @click="$dispatch('cerrar-modal')">Cancelar</button><button class="btn bg-marca-600 text-white hover:bg-marca-700">Rechazar</button></div>
    </form>
</x-modal>
@endcan
@can('movimientos.anular')
<x-modal nombre="anular" titulo="Anular movimiento">
    <form method="POST" action="{{ route('movimientos.anular', $m) }}" class="space-y-4">@csrf
        <p class="text-sm text-carbon-600">Se creará un <b>movimiento de reverso</b> que devuelve las existencias a como estaban. El original queda en el historial como anulado.</p>
        <div><label class="etiqueta">Motivo</label><textarea name="motivo" rows="3" required class="campo"></textarea></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secundario" @click="$dispatch('cerrar-modal')">Cancelar</button><button class="btn bg-marca-600 text-white hover:bg-marca-700">Anular</button></div>
    </form>
</x-modal>
@endcan
</x-layouts.app>
