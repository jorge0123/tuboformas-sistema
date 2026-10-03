<x-layouts.app titulo="Reporte de repuestos">
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-carbon-600">Del <b>@fecha($desde)</b> al <b>@fecha($hasta)</b></p>
    <x-grafica.periodo :desde="$desde" :hasta="$hasta" />
</div>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    @if ($costos)
        <x-kpi titulo="Valor de la bodega hoy" :valor="\App\Support\Formato::dinero($valor)" icono="etiqueta" tono="verde" />
        <x-kpi titulo="Compras en el período" :valor="\App\Support\Formato::dinero($valorMovido['compras'])" icono="camion" tono="azul" />
        <x-kpi titulo="Usado en mantenimiento" :valor="\App\Support\Formato::dinero($valorMovido['mantenimiento'])" icono="llave" />
        <x-kpi titulo="Ajustes de salida (merma)" :valor="\App\Support\Formato::dinero($valorMovido['ajustes'])" icono="alerta" tono="rojo" />
    @else
        <x-kpi titulo="Movimientos en el período" :valor="$porTipo->sum()" icono="flechas" />
        <x-kpi titulo="Recepciones" :valor="$porTipo['entrada_compra'] ?? 0" icono="camion" tono="azul" />
        <x-kpi titulo="Consumos en OT" :valor="$porTipo['consumo_mantenimiento'] ?? 0" icono="llave" />
        <x-kpi titulo="Bajo el mínimo" :valor="$bajoMinimo->count()" icono="alerta" tono="rojo" />
    @endif
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <section class="tarjeta xl:col-span-2">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Repuestos más usados</h3>@if ($costos)<span class="text-xs text-carbon-500">por costo</span>@endif</div>
        <div class="p-5"><x-grafica.barras :datos="$consumido->map(fn ($r) => $costos
            ? ['etiqueta' => $productos[$r->producto_id]?->nombre, 'valor' => (float) $r->valor, 'nota' => \App\Support\Formato::numero($r->total).' '.$productos[$r->producto_id]?->unidad->abreviatura]
            : ['etiqueta' => $productos[$r->producto_id]?->nombre, 'valor' => (float) $r->total, 'nota' => $productos[$r->producto_id]?->unidad->abreviatura])->all()"
            :formato="$costos ? 'dinero' : null" /></div>
    </section>

    <section class="tarjeta overflow-hidden">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Bajo el mínimo</h3><span class="insignia-roja">{{ $bajoMinimo->count() }}</span></div>
        @forelse ($bajoMinimo as $p)
            <a href="{{ route('productos.show', $p) }}" class="flex items-center justify-between gap-3 border-b border-carbon-50 px-5 py-2.5 text-sm last:border-0 hover:bg-carbon-50">
                <span class="truncate">{{ $p->nombre }}</span>
                <span class="shrink-0 tabular-nums"><b class="text-marca-700">@num($p->existencia)</b> / @num($p->stock_minimo) {{ $p->unidad->abreviatura }}</span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-carbon-500">Todo por encima del mínimo.</p>
        @endforelse
    </section>

    @if ($costos)
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Costo de repuestos por máquina</h3></div>
        <div class="p-5"><x-grafica.barras :datos="$porMaquina->map(fn ($r) => ['etiqueta' => $maquinas[$r->maquina_id]?->etiqueta(), 'valor' => (float) $r->total])->all()" formato="dinero" color="bg-sky-500" /></div>
    </section>
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Valor por categoría</h3></div>
        <div class="p-5"><x-grafica.barras :datos="$porCategoria->map(fn ($c) => ['etiqueta' => $c['nombre'], 'valor' => $c['valor']])->all()" formato="dinero" color="bg-emerald-600" /></div>
    </section>
    @endif
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Movimientos por tipo</h3></div>
        <div class="p-5"><x-grafica.barras :datos="$porTipo->map(fn ($n, $t) => ['etiqueta' => \App\Models\Movimiento::TIPOS[$t]['nombre'] ?? $t, 'valor' => $n])->values()->all()" color="bg-carbon-700" /></div>
    </section>
</div>
</x-layouts.app>
