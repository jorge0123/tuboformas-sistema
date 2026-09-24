<x-layouts.app :titulo="$config['nombre']">
<x-slot:migas><a href="{{ route('movimientos.index') }}" class="hover:text-carbon-800">Movimientos</a><x-icono n="derecha" clase="size-3" /><a href="{{ route('movimientos.create') }}" class="hover:text-carbon-800">Nuevo</a><x-icono n="derecha" clase="size-3" />{{ $config['nombre'] }}</x-slot:migas>
@php
    $efecto = $config['efecto'];
    $sale = in_array($efecto, ['salida', 'traslado']);
    $entra = in_array($efecto, ['entrada', 'traslado']);
    $defOrigen = ['salida_produccion' => 'MP', 'salida_despacho' => 'PT', 'ajuste_salida' => 'PT', 'traslado' => 'MP'][$tipo] ?? null;
    $defDestino = ['entrada_compra' => 'MP', 'ingreso_produccion' => 'PT', 'devolucion_produccion' => 'MP', 'ajuste_entrada' => 'PT', 'traslado' => 'PT'][$tipo] ?? null;
    $costos = $tipo === 'entrada_compra' && auth()->user()->can('inventario.ver_costos');
    $filtroTipo = ['ingreso_produccion' => ['producto_terminado'], 'salida_despacho' => ['producto_terminado'], 'salida_produccion' => ['materia_prima', 'insumo'], 'devolucion_produccion' => ['materia_prima', 'insumo']][$tipo] ?? null;
    $lineasOld = old('lineas', $productoInicial ? [['producto_id' => $productoInicial, 'presentacion_id' => '', 'cantidad' => '', 'costo_unitario' => '']] : []);
@endphp

<form method="POST" action="{{ route('movimientos.store') }}" enctype="multipart/form-data" class="mx-auto max-w-5xl space-y-6"
      x-data="movimiento(@js($productos), @js(array_values($lineasOld)), @js($filtroTipo))" @submit="enviando = true">
    @csrf
    <input type="hidden" name="tipo" value="{{ $tipo }}">

    @if (! empty($config['aprobacion']) && ! auth()->user()->can('movimientos.aprobar'))
        <p class="flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            <x-icono n="alerta" clase="mt-0.5 size-4 shrink-0" /> Los ajustes quedan pendientes hasta que un encargado los apruebe. La existencia cambia al aprobarse.
        </p>
    @endif

    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Datos del movimiento</h3><span class="text-xs text-carbon-500">{{ $config['ayuda'] }}</span></div>
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-6">
            <x-campo nombre="fecha" etiqueta="Fecha" tipo="date" :valor="today()->format('Y-m-d')" requerido clase="sm:col-span-2" max="{{ today()->format('Y-m-d') }}" />
            @if ($sale)
            <x-campo nombre="bodega_origen_id" etiqueta="{{ $efecto === 'traslado' ? 'Sale de' : 'Bodega' }}" requerido clase="sm:col-span-2">
                <select id="bodega_origen_id" name="bodega_origen_id" x-model="origen" class="campo" required>
                    @foreach ($bodegas as $b)<option value="{{ $b->id }}" @selected(old('bodega_origen_id') ? old('bodega_origen_id') == $b->id : $b->codigo === $defOrigen)>{{ $b->nombre }}</option>@endforeach
                </select>
            </x-campo>
            @endif
            @if ($entra)
            <x-campo nombre="bodega_destino_id" etiqueta="{{ $efecto === 'traslado' ? 'Entra a' : 'Bodega' }}" requerido clase="sm:col-span-2">
                <select id="bodega_destino_id" name="bodega_destino_id" class="campo" required>
                    @foreach ($bodegas as $b)<option value="{{ $b->id }}" @selected(old('bodega_destino_id') ? old('bodega_destino_id') == $b->id : $b->codigo === $defDestino)>{{ $b->nombre }}</option>@endforeach
                </select>
            </x-campo>
            @endif
            @if ($tipo === 'entrada_compra')
                <x-campo nombre="proveedor_id" etiqueta="Proveedor" clase="sm:col-span-2">
                    <select id="proveedor_id" name="proveedor_id" class="campo"><option value="">Sin proveedor</option>@foreach ($proveedores as $p)<option value="{{ $p->id }}" @selected(old('proveedor_id') == $p->id)>{{ $p->nombre }}</option>@endforeach</select>
                </x-campo>
                <x-campo nombre="documento" etiqueta="Factura / envío" clase="sm:col-span-2" placeholder="FAC-1234" />
            @elseif ($tipo === 'salida_produccion' || $tipo === 'devolucion_produccion')
                <x-campo nombre="maquina_id" etiqueta="Máquina / línea" clase="sm:col-span-2">
                    <select id="maquina_id" name="maquina_id" class="campo"><option value="">Sin especificar</option>@foreach ($maquinas as $m)<option value="{{ $m->id }}" @selected(old('maquina_id') == $m->id)>{{ $m->codigo ? $m->codigo.' · ' : '' }}{{ $m->nombre }}</option>@endforeach</select>
                </x-campo>
                <x-campo nombre="referencia" etiqueta="Operador que recibe" clase="sm:col-span-2" placeholder="Nombre del operador" />
            @elseif ($tipo === 'salida_despacho')
                <x-campo nombre="referencia" etiqueta="Cliente / destino" clase="sm:col-span-2" />
                <x-campo nombre="documento" etiqueta="Envío / factura" clase="sm:col-span-2" />
            @else
                <x-campo nombre="referencia" etiqueta="Referencia" clase="sm:col-span-2" placeholder="{{ $tipo === 'ingreso_produccion' ? 'Mesa / turno' : 'Opcional' }}" />
            @endif
            <x-campo nombre="notas" etiqueta="{{ str_starts_with($tipo, 'ajuste') ? 'Motivo del ajuste' : 'Notas' }}" clase="sm:col-span-6">
                <textarea id="notas" name="notas" rows="2" class="campo" @required(str_starts_with($tipo, 'ajuste'))>{{ old('notas') }}</textarea>
            </x-campo>
        </div>
    </section>

    {{-- Productos --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza">
            <h3 class="tarjeta-titulo">Productos</h3>
            <span class="text-xs text-carbon-500" x-text="lineas.length + (lineas.length === 1 ? ' producto' : ' productos')"></span>
        </div>
        <div class="divide-y divide-carbon-100">
            <template x-for="(l, i) in lineas" :key="l.clave">
                <div class="grid grid-cols-12 items-start gap-3 p-4">
                    {{-- Buscador de producto --}}
                    <div class="relative col-span-12 md:col-span-5" @click.outside="l.abierto = false">
                        <label class="etiqueta">Producto</label>
                        <input type="hidden" :name="`lineas[${i}][producto_id]`" :value="l.producto_id">
                        <button type="button" class="campo flex items-center justify-between text-left" @click="l.abierto = !l.abierto; $nextTick(() => $el.parentElement.querySelector('input[type=search]')?.focus())">
                            <span class="truncate" :class="!l.producto_id && 'text-carbon-400'" x-text="l.producto_id ? etiqueta(l.producto_id) : 'Buscar producto…'"></span>
                            <x-icono n="abajo" clase="size-4 shrink-0 text-carbon-400" />
                        </button>
                        <div x-show="l.abierto" x-cloak x-transition.origin.top class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-carbon-200">
                            <div class="border-b border-carbon-100 p-2"><input type="search" x-model="l.busqueda" class="campo py-1.5" placeholder="Código, nombre o medida…"></div>
                            <ul class="max-h-64 overflow-y-auto py-1">
                                <template x-for="p in filtrar(l.busqueda)" :key="p.id">
                                    <li><button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-carbon-50" @click="elegir(l, p)">
                                        <span class="min-w-0"><span class="block truncate font-medium" x-text="p.nombre"></span><span class="font-mono text-xs text-carbon-400" x-text="p.codigo"></span></span>
                                        <span class="shrink-0 text-xs text-carbon-500" x-text="fmt(stockDe(p, origen)) + ' ' + p.unidad" x-show="origen"></span>
                                    </button></li>
                                </template>
                                <li x-show="!filtrar(l.busqueda).length" class="px-3 py-4 text-center text-sm text-carbon-500">Sin resultados</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="etiqueta">Se cuenta en</label>
                        <select :name="`lineas[${i}][presentacion_id]`" x-model="l.presentacion_id" class="campo" :disabled="!l.producto_id">
                            <option value="" x-text="l.producto_id ? unidadDe(l.producto_id) : '—'"></option>
                            <template x-for="pr in presentacionesDe(l.producto_id)" :key="pr.id">
                                <option :value="pr.id" x-text="pr.nombre + ' (' + fmt(pr.factor) + ')'" :selected="pr.id == l.presentacion_id"></option>
                            </template>
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="etiqueta">Cantidad</label>
                        <input :name="`lineas[${i}][cantidad]`" x-model="l.cantidad" type="number" step="any" min="0" inputmode="decimal" required class="campo text-right font-semibold" :class="excede(l) && 'campo-error'">
                    </div>
                    @if ($costos)
                    <div class="col-span-6 md:col-span-1">
                        <label class="etiqueta">Costo</label>
                        <input :name="`lineas[${i}][costo_unitario]`" x-model="l.costo_unitario" type="number" step="any" min="0" class="campo px-2 text-right" placeholder="Q">
                    </div>
                    @endif
                    <div class="col-span-6 flex items-end justify-end {{ $costos ? 'md:col-span-1' : 'md:col-span-2' }} md:pt-6">
                        <button type="button" class="btn-fantasma btn-icono text-carbon-400 hover:text-marca-700" @click="quitar(i)" aria-label="Quitar"><x-icono n="basura" clase="size-4" /></button>
                    </div>
                    <p class="col-span-12 -mt-1 text-xs" x-show="l.producto_id && l.cantidad > 0" x-cloak>
                        <span class="text-carbon-500">= <b class="text-carbon-800" x-text="fmt(base(l)) + ' ' + unidadDe(l.producto_id)"></b></span>
                        @if ($sale)
                        <span class="ml-2" :class="excede(l) ? 'font-semibold text-marca-700' : 'text-carbon-500'"
                              x-text="'Disponible en bodega: ' + fmt(stockDe(producto(l.producto_id), origen)) + (excede(l) ? ' · no alcanza' : '')"></span>
                        @endif
                    </p>
                </div>
            </template>
        </div>
        <div class="border-t border-carbon-100 p-4">
            <button type="button" class="btn-secundario" @click="agregar()"><x-icono n="mas" clase="size-4" /> Agregar producto</button>
        </div>
    </section>

    {{-- Evidencia --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Fotos de evidencia</h3><p class="text-xs text-carbon-500">Ej. la tarima recibida, las bolsas contadas, el vale firmado.</p></div></div>
        <div class="p-5">
            <input type="file" name="fotos[]" multiple accept="image/*" class="hidden" x-ref="fotos">
            <div class="flex flex-wrap gap-3">
                <template x-for="(f, i) in vistas" :key="i">
                    <div class="group relative size-24 overflow-hidden rounded-xl ring-1 ring-carbon-200">
                        <img :src="f" class="size-full object-cover">
                        <button type="button" class="absolute top-1 right-1 grid size-6 place-items-center rounded-md bg-white/90 text-marca-700 shadow" @click="quitarFoto(i)"><x-icono n="x" clase="size-3.5" /></button>
                    </div>
                </template>
                <label class="grid size-24 cursor-pointer place-items-center rounded-xl border-2 border-dashed border-carbon-300 text-carbon-500 transition hover:border-marca-400 hover:bg-marca-50/40 hover:text-marca-700">
                    <span class="flex flex-col items-center gap-1 text-xs font-semibold"><x-icono n="camara" clase="size-6" />Tomar foto</span>
                    <input type="file" accept="image/*" capture="environment" class="sr-only" @change="agregarFotos($event)">
                </label>
                <label class="grid size-24 cursor-pointer place-items-center rounded-xl border-2 border-dashed border-carbon-300 text-carbon-500 transition hover:border-marca-400 hover:bg-marca-50/40 hover:text-marca-700">
                    <span class="flex flex-col items-center gap-1 text-xs font-semibold"><x-icono n="imagen" clase="size-6" />Galería</span>
                    <input type="file" accept="image/*" multiple class="sr-only" @change="agregarFotos($event)">
                </label>
            </div>
        </div>
    </section>

    <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 rounded-xl border border-carbon-200 bg-white/95 px-3 py-3 shadow-lg backdrop-blur">
        <a href="{{ route('movimientos.create') }}" class="btn-secundario">Cambiar tipo</a>
        <button class="btn-primario" :disabled="enviando || !valido()">
            <x-icono n="check" clase="size-4" />
            <span x-text="enviando ? 'Guardando…' : 'Registrar'" class="sm:hidden"></span>
            <span x-text="enviando ? 'Guardando…' : 'Registrar {{ mb_strtolower($config['nombre']) }}'" class="hidden sm:inline"></span>
        </button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('movimiento', (productos, iniciales, filtroTipo) => ({
        productos, filtroTipo, enviando: false, origen: null, vistas: [], archivos: new DataTransfer(), n: 0,
        lineas: [],
        init() {
            this.origen = this.$root.querySelector('[name=bodega_origen_id]')?.value ?? null;
            (iniciales.length ? iniciales : [{}]).forEach(l => this.agregar(l));
        },
        nueva(l = {}) { return { clave: ++this.n, producto_id: l.producto_id ?? '', presentacion_id: l.presentacion_id ?? '', cantidad: l.cantidad ?? '', costo_unitario: l.costo_unitario ?? '', busqueda: '', abierto: false }; },
        agregar(l = {}) { this.lineas.push(this.nueva(l)); },
        quitar(i) { this.lineas.splice(i, 1); if (!this.lineas.length) this.agregar(); },
        producto(id) { return this.productos.find(p => p.id == id); },
        etiqueta(id) { const p = this.producto(id); return p ? p.codigo + ' · ' + p.nombre : ''; },
        unidadDe(id) { return this.producto(id)?.unidad ?? ''; },
        presentacionesDe(id) { return this.producto(id)?.presentaciones ?? []; },
        stockDe(p, bodega) { return p && bodega ? (p.stock[bodega] ?? 0) : 0; },
        filtrar(q) {
            q = (q || '').toLowerCase().trim();
            const usados = this.lineas.map(l => +l.producto_id);
            return this.productos.filter(p => (!this.filtroTipo || this.filtroTipo.includes(p.tipo))
                && (!q || (p.codigo + ' ' + p.nombre).toLowerCase().includes(q))).slice(0, 40)
                .sort((a, b) => usados.includes(a.id) - usados.includes(b.id));
        },
        elegir(l, p) {
            l.producto_id = p.id; l.abierto = false; l.busqueda = '';
            // Por defecto la primera presentación (bolsa x 300, manojo): es como se cuenta en físico.
            l.presentacion_id = p.presentaciones.length ? p.presentaciones[0].id : '';
            this.$nextTick(() => this.$root.querySelectorAll('input[type=number][name$="[cantidad]"]')[this.lineas.indexOf(l)]?.focus());
        },
        factor(l) { return this.presentacionesDe(l.producto_id).find(x => x.id == l.presentacion_id)?.factor ?? 1; },
        base(l) { return (parseFloat(l.cantidad) || 0) * this.factor(l); },
        excede(l) {
            if (!this.origen || !l.producto_id) return false;
            const total = this.lineas.filter(x => x.producto_id == l.producto_id).reduce((s, x) => s + this.base(x), 0);
            return total > this.stockDe(this.producto(l.producto_id), this.origen) + 1e-9;
        },
        valido() { return this.lineas.every(l => l.producto_id && parseFloat(l.cantidad) > 0 && !this.excede(l)); },
        fmt(n) { return Number(n).toLocaleString('es-GT', { maximumFractionDigits: 3 }); },
        async agregarFotos(e) {
            for (const f of e.target.files) {
                const c = await comprimirImagen(f);
                this.archivos.items.add(c);
                this.vistas.push(URL.createObjectURL(c));
            }
            this.$refs.fotos.files = this.archivos.files;
            e.target.value = '';
        },
        quitarFoto(i) {
            this.archivos.items.remove(i);
            this.vistas.splice(i, 1);
            this.$refs.fotos.files = this.archivos.files;
        },
    }));
});
</script>
@endpush
</x-layouts.app>
