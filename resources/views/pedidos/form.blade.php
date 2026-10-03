<x-layouts.app :titulo="$p->exists ? 'Editar '.$p->folio : 'Nuevo pedido'">
<x-slot:migas><a href="{{ route('pedidos.index') }}" class="hover:text-carbon-800">Pedidos</a><x-icono n="derecha" clase="size-3" />{{ $p->exists ? $p->folio : 'Nuevo' }}</x-slot:migas>
@php
    $lineasIniciales = old('lineas', $p->exists ? $p->lineas->map(fn ($l) => ['producto_id' => $l->producto_id, 'presentacion_id' => $l->presentacion_id ?? '', 'cantidad' => (float) $l->cantidad, 'notas' => $l->notas])->all() : []);
@endphp

<form method="POST" action="{{ $p->exists ? route('pedidos.update', $p) : route('pedidos.store') }}" class="mx-auto max-w-4xl space-y-5"
      x-data="formPedido({ clientes: @js($clientes), productos: @js($productos), clienteInicial: @js((int) old('cliente_id', $p->cliente_id) ?: null),
                           lineas: @js(array_values($lineasIniciales)), bodega: @js((int) old('bodega_id', $bodegaInicial)), urlCliente: @js(route('clientes.store')) })"
      @input="$event.target.dataset.tocado = 1">
    @csrf
    @if ($p->exists) @method('PUT') @endif

    {{-- 1. Cliente --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo"><span class="mr-1.5 text-carbon-400">1</span> Cliente</h3></div>
        <div class="p-4 sm:p-5">
            <input type="hidden" name="cliente_id" :value="clienteId">
            <div class="relative" @click.outside="abiertoCliente = false">
                <template x-if="cliente">
                    <div class="flex items-center gap-3 rounded-xl bg-carbon-50 p-3 ring-1 ring-carbon-200">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-carbon-900 text-sm font-bold text-white" x-text="cliente.nombre.slice(0, 2).toUpperCase()"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold" x-text="cliente.nombre"></p>
                            <p class="truncate text-xs text-carbon-500" x-text="[cliente.nit && 'NIT ' + cliente.nit, cliente.telefono, cliente.municipio].filter(Boolean).join(' · ')"></p>
                        </div>
                        <button type="button" class="btn-fantasma btn-sm" @click="clienteId = null; $nextTick(() => $refs.buscarCliente.focus())">Cambiar</button>
                    </div>
                </template>
                <div x-show="!cliente">
                    <div class="relative">
                        <x-icono n="buscar" clase="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-carbon-400" />
                        <input x-ref="buscarCliente" x-model="buscarCliente" @focus="abiertoCliente = true" @input="abiertoCliente = true" type="search" autocomplete="off"
                               @keydown.enter.prevent="clientesFiltrados[0] && elegirCliente(clientesFiltrados[0])"
                               class="campo h-11 pl-9 @error('cliente_id') campo-error @enderror" placeholder="Buscar cliente por nombre, NIT o municipio…">
                    </div>
                    <ul x-show="abiertoCliente" x-cloak class="scroll-fino absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-xl bg-white py-1 shadow-xl ring-1 ring-carbon-200">
                        <template x-for="c in clientesFiltrados" :key="c.id">
                            <li><button type="button" class="w-full px-3 py-2 text-left hover:bg-carbon-50" @click="elegirCliente(c)">
                                <span class="block truncate text-sm font-semibold" x-text="c.nombre"></span>
                                <span class="block truncate text-xs text-carbon-500" x-text="[c.nit && 'NIT ' + c.nit, c.municipio].filter(Boolean).join(' · ')"></span>
                            </button></li>
                        </template>
                        @can('clientes.gestionar')
                            <li class="border-t border-carbon-100"><button type="button" class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm font-semibold text-marca-700 hover:bg-marca-50"
                                @click="nuevo = { nombre: buscarCliente, nit: '', telefono: '', contacto: '', direccion: '', municipio: '' }; abiertoCliente = false; $nextTick(() => $refs.nuevoNombre.focus())">
                                <x-icono n="mas" clase="size-4" /> <span x-text="buscarCliente ? `Registrar «${buscarCliente}» como cliente nuevo` : 'Registrar cliente nuevo'"></span>
                            </button></li>
                        @endcan
                    </ul>
                    @error('cliente_id')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Alta rápida sin salir del pedido --}}
            <div x-show="nuevo" x-cloak x-collapse class="mt-3">
                <template x-if="nuevo">
                <div class="grid grid-cols-1 gap-3 rounded-xl bg-carbon-50 p-4 ring-1 ring-carbon-100 sm:grid-cols-6" @keydown.enter.prevent="guardarCliente()">
                    <p class="font-semibold sm:col-span-6">Cliente nuevo</p>
                    <div class="sm:col-span-4"><label class="etiqueta">Nombre o razón social *</label><input x-ref="nuevoNombre" x-model="nuevo.nombre" class="campo"></div>
                    <div class="sm:col-span-2"><label class="etiqueta">NIT</label><input x-model="nuevo.nit" class="campo" placeholder="CF"></div>
                    <div class="sm:col-span-2"><label class="etiqueta">Contacto</label><input x-model="nuevo.contacto" class="campo"></div>
                    <div class="sm:col-span-2"><label class="etiqueta">Teléfono</label><input x-model="nuevo.telefono" type="tel" inputmode="tel" class="campo"></div>
                    <div class="sm:col-span-2"><label class="etiqueta">Municipio</label><input x-model="nuevo.municipio" class="campo"></div>
                    <div class="sm:col-span-6"><label class="etiqueta">Dirección</label><input x-model="nuevo.direccion" class="campo"></div>
                    <div class="flex justify-end gap-2 sm:col-span-6">
                        <button type="button" class="btn-fantasma" @click="nuevo = null">Cancelar</button>
                        <button type="button" class="btn-oscuro" @click="guardarCliente()" :disabled="!nuevo?.nombre?.trim()">Guardar cliente</button>
                    </div>
                </div>
                </template>
            </div>
        </div>
    </section>

    {{-- 2. Entrega --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo"><span class="mr-1.5 text-carbon-400">2</span> Entrega</h3></div>
        <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-6 sm:p-5" x-data="{ tipo: @js(old('tipo_entrega', $p->tipo_entrega)), urgente: @js(old('prioridad', $p->prioridad) === 'urgente') }">
            <div class="col-span-2 sm:col-span-6">
                <span class="etiqueta">¿Cómo se entrega?</span>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach (\App\Models\Pedido::TIPOS_ENTREGA as $k => $v)
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl px-3 py-2.5 ring-1 ring-carbon-200 transition has-checked:bg-marca-50 has-checked:ring-2 has-checked:ring-marca-500">
                            <input type="radio" name="tipo_entrega" value="{{ $k }}" x-model="tipo" class="mt-0.5 text-marca-600 focus:ring-marca-500">
                            <span class="min-w-0">
                                <span class="flex items-center gap-1.5 text-sm font-semibold"><x-icono :n="['ruta' => 'camion', 'recoge' => 'fabrica', 'transporte' => 'enviar'][$k]" clase="size-4 text-carbon-500" /> {{ $v }}</span>
                                <span class="mt-0.5 block text-xs leading-snug text-carbon-500">{{ \App\Models\Pedido::AYUDA_ENTREGA[$k] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
            <x-campo nombre="fecha_entrega" etiqueta="Fecha de entrega" tipo="date" :valor="$p->fecha_entrega?->format('Y-m-d')" requerido clase="col-span-1 sm:col-span-2" />
            <x-campo nombre="jornada" etiqueta="Jornada" clase="col-span-1 sm:col-span-2">
                <select id="jornada" name="jornada" class="campo"><option value="">Cualquier hora</option>@foreach (\App\Models\Pedido::JORNADAS as $k => $v)<option value="{{ $k }}" @selected(old('jornada', $p->jornada) === $k)>{{ $v }}</option>@endforeach</select>
            </x-campo>
            <div class="col-span-2 sm:col-span-2">
                <span class="etiqueta">Prioridad</span>
                <input type="hidden" name="prioridad" :value="urgente ? 'urgente' : 'normal'">
                <button type="button" @click="urgente = !urgente" class="flex h-[2.625rem] w-full items-center justify-between rounded-lg px-3 text-sm font-semibold ring-1 transition"
                        :class="urgente ? 'bg-marca-600 text-white ring-marca-600' : 'bg-white text-carbon-700 ring-carbon-200'" :aria-pressed="urgente.toString()">
                    <span class="flex items-center gap-2"><x-icono n="rayo" clase="size-4" /> <span x-text="urgente ? 'Urgente' : 'Normal'"></span></span>
                    <span class="relative h-5 w-9 rounded-full transition" :class="urgente ? 'bg-white/30' : 'bg-carbon-200'"><span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :class="urgente ? 'left-[1.125rem]' : 'left-0.5'"></span></span>
                </button>
            </div>
            <div class="col-span-2 sm:col-span-6" x-show="tipo !== 'recoge'" x-collapse>
                <x-campo nombre="direccion_entrega" etiqueta="Dirección de entrega" :valor="$p->direccion_entrega" placeholder="Se llena con la del cliente" />
            </div>
            <x-campo nombre="contacto_nombre" etiqueta="Recibe" :valor="$p->contacto_nombre" clase="col-span-1 sm:col-span-3" placeholder="Nombre" />
            <x-campo nombre="contacto_telefono" etiqueta="Teléfono" tipo="tel" :valor="$p->contacto_telefono" clase="col-span-1 sm:col-span-3" inputmode="tel" />
        </div>
    </section>

    {{-- 3. Productos --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza">
            <h3 class="tarjeta-titulo"><span class="mr-1.5 text-carbon-400">3</span> Productos</h3>
            <select name="bodega_id" x-model.number="bodega" class="campo w-auto py-1 text-xs" aria-label="Sale de">
                @foreach ($bodegas as $b)<option value="{{ $b->id }}">{{ $b->nombre }}</option>@endforeach
            </select>
        </div>
        <div class="divide-y divide-carbon-100">
            <template x-for="(l, i) in lineas" :key="l.clave">
                <div class="grid grid-cols-12 items-start gap-3 p-4">
                    <div class="relative col-span-12 md:col-span-6" @click.outside="l.abierto = false">
                        <input type="hidden" :name="`lineas[${i}][producto_id]`" :value="l.producto_id">
                        <template x-if="l.producto_id">
                            <button type="button" class="campo flex items-center justify-between gap-2 text-left" @click="l.producto_id = ''; l.abierto = true; $nextTick(() => $el.parentElement.parentElement.querySelector('[data-buscar-linea]')?.focus())">
                                <span class="min-w-0"><span class="block truncate font-semibold" x-text="producto(l.producto_id)?.nombre"></span></span>
                                <x-icono n="x" clase="size-4 shrink-0 text-carbon-400" />
                            </button>
                        </template>
                        <div x-show="!l.producto_id" class="relative">
                            <x-icono n="buscar" clase="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-carbon-400" />
                            <input data-buscar-linea x-model="l.busqueda" @focus="l.abierto = true" @input="l.abierto = true" type="search" autocomplete="off" class="campo pl-9"
                                   placeholder="Producto: copla 3/4 naranja…" @keydown.enter.prevent="filtrar(l.busqueda)[0] && elegir(l, filtrar(l.busqueda)[0])">
                        </div>
                        <ul x-show="l.abierto && !l.producto_id" x-cloak class="scroll-fino absolute inset-x-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-xl bg-white py-1 shadow-xl ring-1 ring-carbon-200">
                            <template x-for="pr in filtrar(l.busqueda)" :key="pr.id">
                                <li><button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-carbon-50" @click="elegir(l, pr)">
                                    <span class="size-2.5 shrink-0 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(pr.color)}`"></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium" x-text="pr.nombre"></span><span class="font-mono text-[11px] text-carbon-400" x-text="pr.codigo"></span></span>
                                    <span class="shrink-0 text-xs tabular-nums" :class="(pr.stock[bodega] || 0) > 0 ? 'text-carbon-500' : 'text-marca-600'" x-text="fmt(pr.stock[bodega] || 0) + ' ' + pr.unidad"></span>
                                </button></li>
                            </template>
                            <li x-show="!filtrar(l.busqueda).length" class="px-3 py-4 text-center text-sm text-carbon-500">Sin resultados</li>
                        </ul>
                    </div>
                    <div class="col-span-5 md:col-span-3">
                        <select :name="`lineas[${i}][presentacion_id]`" x-model="l.presentacion_id" class="campo" :disabled="!l.producto_id" aria-label="Presentación">
                            <option value="" x-text="l.producto_id ? 'Suelto (' + producto(l.producto_id).unidad + ')' : 'Presentación'"></option>
                            <template x-for="pr in producto(l.producto_id)?.presentaciones || []" :key="pr.id">
                                <option :value="pr.id" x-text="pr.nombre" :selected="pr.id == l.presentacion_id"></option>
                            </template>
                        </select>
                    </div>
                    <div class="col-span-5 md:col-span-2">
                        <input :name="`lineas[${i}][cantidad]`" :data-cantidad="l.clave" x-model="l.cantidad" type="number" step="any" min="0" inputmode="decimal" required placeholder="Cant."
                               class="campo text-right font-semibold tabular-nums" :class="excede(l) && 'ring-amber-400'" @keydown.enter.prevent="agregar()">
                    </div>
                    <div class="col-span-2 flex justify-end md:col-span-1">
                        <button type="button" class="btn-fantasma btn-icono text-carbon-400 hover:text-marca-700" @click="quitar(i)" aria-label="Quitar"><x-icono n="basura" clase="size-4" /></button>
                    </div>
                    <p class="col-span-12 -mt-1 flex flex-wrap gap-x-3 text-xs" x-show="l.producto_id && l.cantidad > 0" x-cloak>
                        <span class="text-carbon-500">= <b class="text-carbon-800" x-text="fmt(base(l)) + ' ' + producto(l.producto_id)?.unidad"></b></span>
                        <span :class="excede(l) ? 'font-semibold text-amber-700' : 'text-carbon-500'"
                              x-text="excede(l) ? `Hay ${fmt(stock(l))} en bodega: se puede pedir, pero bodega tendrá que completarlo` : `Disponible: ${fmt(stock(l))}`"></span>
                    </p>
                    <input type="hidden" :name="`lineas[${i}][notas]`" :value="l.notas">
                </div>
            </template>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-carbon-100 p-4">
            <button type="button" class="btn-secundario" @click="agregar()"><x-icono n="mas" clase="size-4" /> Agregar producto</button>
            <p class="text-sm text-carbon-500" x-show="totalUnidades > 0"><b class="text-carbon-900" x-text="lineas.filter(l => l.producto_id).length"></b> productos · <b class="text-carbon-900" x-text="fmt(totalUnidades)"></b> unidades</p>
        </div>
        @error('lineas')<p class="error px-4 pb-4">{{ $message }}</p>@enderror
    </section>

    {{-- 4. Datos comerciales --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo"><span class="mr-1.5 text-carbon-400">4</span> Datos comerciales <span class="text-xs font-normal text-carbon-400">(opcional)</span></h3></div>
        <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-6 sm:p-5">
            <x-campo nombre="orden_compra" etiqueta="Orden de compra del cliente" :valor="$p->orden_compra" clase="col-span-1 sm:col-span-3" placeholder="OC-1234" />
            <x-campo nombre="condicion_pago" etiqueta="Pago" clase="col-span-1 sm:col-span-3">
                <select id="condicion_pago" name="condicion_pago" class="campo"><option value="">—</option>@foreach (\App\Models\Pedido::CONDICIONES_PAGO as $k => $v)<option value="{{ $k }}" @selected(old('condicion_pago', $p->condicion_pago) === $k)>{{ $v }}</option>@endforeach</select>
            </x-campo>
            <x-campo nombre="notas" etiqueta="Indicaciones para bodega" clase="col-span-2 sm:col-span-6"><textarea id="notas" name="notas" rows="2" class="campo" placeholder="Ej. empacar en cajas, llamar antes de llegar, factura a nombre de…">{{ old('notas', $p->notas) }}</textarea></x-campo>
        </div>
    </section>

    <div class="sobre-barra sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-2 border-t border-carbon-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border">
        <a href="{{ $p->exists ? route('pedidos.show', $p) : route('pedidos.index') }}" class="btn-secundario">Cancelar</a>
        <button class="btn-primario h-11 px-5"><x-icono n="enviar" clase="size-4" /> {{ $p->exists ? 'Guardar cambios' : 'Enviar a bodega' }}</button>
    </div>
</form>
</x-layouts.app>
