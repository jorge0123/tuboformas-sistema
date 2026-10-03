<x-layouts.app titulo="Etiquetas QR">
<x-slot:migas><a href="{{ route('productos.index') }}" class="hover:text-carbon-800">Bodega</a><x-icono n="derecha" clase="size-3" />Etiquetas QR</x-slot:migas>

@push('scripts')
<style>
    /* Al imprimir solo sale la hoja de etiquetas. */
    @media print {
        @page { size: letter; margin: 8mm; }
        body * { visibility: hidden !important; }
        .hoja-imprimible, .hoja-imprimible * { visibility: visible !important; }
        .hoja-imprimible { position: absolute; inset: 0 auto auto 0; width: 100%; padding: 0 !important; box-shadow: none !important; background: #fff !important; }
        .etiqueta-qr { break-inside: avoid; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

<div x-data="hojaEtiquetas({ catalogo: @js($catalogo), inicial: @js($inicial) })" class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">

    {{-- Armar la hoja --}}
    <div class="no-imprimir space-y-4">
        <section class="tarjeta aparecer">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Agregar etiquetas</h3></div>
            <div class="space-y-4 p-4">
                {{-- Producto --}}
                <div class="relative" @click.outside="abierto = false">
                    <label class="etiqueta" for="buscar-producto">Producto</label>
                    <template x-if="seleccionado">
                        <div class="flex items-center gap-3 rounded-lg bg-carbon-50 px-3 py-2.5 ring-1 ring-carbon-200">
                            <span class="size-3.5 shrink-0 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(seleccionado.color)}`"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold" x-text="nombre(seleccionado)"></span>
                                <span class="block font-mono text-xs text-carbon-500" x-text="seleccionado.codigo"></span>
                            </span>
                            <button type="button" class="rounded-md p-1 text-carbon-400 hover:bg-carbon-200 hover:text-carbon-700" @click="sel.producto_id = null; $nextTick(() => $refs.buscar.focus())" aria-label="Cambiar producto"><x-icono n="x" clase="size-4" /></button>
                        </div>
                    </template>
                    <div x-show="!seleccionado" class="relative">
                        <x-icono n="buscar" clase="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-carbon-400" />
                        <input id="buscar-producto" x-ref="buscar" x-model="q" @click="abierto = true" @input="abierto = true" @keydown.down="abierto = true" type="search" autocomplete="off"
                               @keydown.enter.prevent="sugerencias[0] && elegir(sugerencias[0])" @keydown.escape="abierto = false"
                               class="campo h-11 pl-9" placeholder="copla 3/4 gris, PT-COP…">
                        <ul x-show="abierto" x-cloak x-transition.origin.top class="scroll-fino absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-xl bg-white py-1 shadow-xl ring-1 ring-carbon-200">
                            <template x-for="p in sugerencias" :key="p.id">
                                <li>
                                    <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-carbon-50" @click="elegir(p)">
                                        <span class="size-3 shrink-0 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(p.color)}`"></span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium" x-text="nombre(p)"></span><span class="block font-mono text-[11px] text-carbon-500" x-text="p.codigo"></span></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="!sugerencias.length" class="px-3 py-4 text-center text-sm text-carbon-500">No existe ese producto. Créalo primero en Inventario.</li>
                        </ul>
                    </div>
                </div>

                {{-- Presentación --}}
                <div x-show="seleccionado" x-transition>
                    <p class="etiqueta">Cada etiqueta es para</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="pr in seleccionado?.presentaciones || []" :key="pr.id">
                            <button type="button" @click="sel.presentacion_id = pr.id" class="h-10 rounded-lg px-3 text-sm font-semibold ring-1 transition active:scale-95"
                                    :class="sel.presentacion_id === pr.id ? 'bg-marca-600 text-white ring-marca-600' : 'bg-white text-carbon-700 ring-carbon-200 hover:ring-carbon-400'" x-text="pr.nombre"></button>
                        </template>
                        <button type="button" @click="sel.presentacion_id = null" class="h-10 rounded-lg px-3 text-sm font-semibold ring-1 transition active:scale-95"
                                :class="sel.presentacion_id === null ? 'bg-marca-600 text-white ring-marca-600' : 'bg-white text-carbon-700 ring-carbon-200 hover:ring-carbon-400'" x-text="`Unidad (${seleccionado?.unidad})`"></button>
                        <button type="button" @click="sel.presentacion_id = 'otra'; $nextTick(() => $refs.cantidad.focus())" class="flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold ring-1 transition active:scale-95"
                                :class="sel.presentacion_id === 'otra' ? 'bg-marca-600 text-white ring-marca-600' : 'bg-white text-carbon-700 ring-1 ring-dashed ring-carbon-300 hover:ring-carbon-400'">
                            <x-icono n="lapiz" clase="size-3.5" /> Personalizada
                        </button>
                    </div>
                    {{-- Empaque a la medida: "Paquete x 4 pza" --}}
                    <div x-show="sel.presentacion_id === 'otra'" x-collapse class="mt-3">
                        <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-2 rounded-xl bg-carbon-50 p-3 ring-1 ring-carbon-100">
                            <div>
                                <label class="etiqueta" for="empaque">Empaque</label>
                                <input id="empaque" x-model="sel.empaque" list="empaques" class="campo" placeholder="Paquete" maxlength="20">
                                <datalist id="empaques"><option value="Paquete"><option value="Bolsa"><option value="Caja"><option value="Fardo"><option value="Manojo"><option value="Rollo"></datalist>
                            </div>
                            <div>
                                <label class="etiqueta" for="cantidad" x-text="`Trae (${seleccionado?.unidad})`"></label>
                                <input id="cantidad" x-ref="cantidad" x-model.number="sel.cantidad" type="number" inputmode="decimal" min="0" step="any" class="campo text-center font-bold tabular-nums" placeholder="4" @keydown.enter.prevent="agregar()">
                            </div>
                            <p class="col-span-2 text-xs text-carbon-500" x-show="sel.cantidad > 0">La etiqueta dirá <b class="text-carbon-800" x-text="`${sel.empaque || 'Paquete'} x ${sel.cantidad} ${seleccionado?.unidad}`"></b> y al escanearla se propondrán <b class="text-carbon-800" x-text="`${sel.cantidad} ${seleccionado?.unidad}`"></b>.</p>
                        </div>
                    </div>
                </div>

                {{-- Copias --}}
                <div x-show="seleccionado" x-transition>
                    <label class="etiqueta" for="copias">¿Cuántas etiquetas?</label>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center rounded-lg ring-1 ring-carbon-200">
                            <button type="button" class="grid size-10 place-items-center text-carbon-600 active:bg-carbon-100" @click="sel.copias = Math.max(1, (+sel.copias || 0) - 1)" aria-label="Menos"><x-icono n="menos" clase="size-4" /></button>
                            <input id="copias" x-ref="copias" type="number" inputmode="numeric" min="1" max="500" x-model.number="sel.copias" @keydown.enter.prevent="agregar()"
                                   class="w-16 border-0 bg-transparent p-0 text-center text-lg font-bold tabular-nums focus:ring-0">
                            <button type="button" class="grid size-10 place-items-center text-carbon-600 active:bg-carbon-100" @click="sel.copias = Math.min(500, (+sel.copias || 0) + 1)" aria-label="Más"><x-icono n="mas" clase="size-4" /></button>
                        </div>
                        @foreach ([10, 20, 50] as $n)
                            <button type="button" class="h-10 rounded-lg px-3 text-sm font-semibold ring-1 transition active:scale-95" @click="sel.copias = {{ $n }}"
                                    :class="+sel.copias === {{ $n }} ? 'bg-carbon-900 text-white ring-carbon-900' : 'bg-white text-carbon-600 ring-carbon-200 hover:ring-carbon-400'">{{ $n }}</button>
                        @endforeach
                    </div>
                </div>

                <button type="button" class="btn-primario h-11 w-full" @click="agregar()" :disabled="!seleccionado">
                    <x-icono n="mas" clase="size-4" />
                    <span x-text="seleccionado ? `Agregar ${+sel.copias || 0} a la hoja` : 'Agregar a la hoja'"></span>
                </button>
            </div>
        </section>

        {{-- Lo que lleva la hoja --}}
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">En la hoja</h3>
                <button type="button" x-show="cola.length" class="text-xs font-semibold text-carbon-500 hover:text-marca-700" @click="vaciar()">Vaciar</button>
            </div>
            <p x-show="!cola.length" class="px-4 py-6 text-center text-sm text-carbon-500">Agrega productos para armar la hoja.</p>
            <ul class="divide-y divide-carbon-100">
                <template x-for="(i, n) in cola" :key="texto(i) + (i.empaque || '')">
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <span class="size-3 shrink-0 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(producto(i.producto_id).color)}`"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold" x-text="nombre(producto(i.producto_id))"></p>
                            <p class="text-xs text-carbon-500" x-text="banda(i)"></p>
                        </div>
                        <input type="number" min="1" max="500" x-model.number="i.copias" class="campo w-16 py-1 text-center text-sm font-bold tabular-nums" aria-label="Copias">
                        <button type="button" class="rounded-md p-1.5 text-carbon-400 hover:bg-marca-50 hover:text-marca-700" @click="quitar(n)" aria-label="Quitar"><x-icono n="basura" clase="size-4" /></button>
                    </li>
                </template>
            </ul>
            <div class="space-y-3 border-t border-carbon-100 p-4" x-show="cola.length">
                <div>
                    <p class="etiqueta">Tamaño de etiqueta</p>
                    <div class="grid grid-cols-3 gap-1 rounded-lg bg-carbon-100 p-1">
                        @foreach (['pequena' => 'Pequeña', 'mediana' => 'Mediana', 'grande' => 'Grande'] as $k => $v)
                            <button type="button" @click="tamano = '{{ $k }}'" class="rounded-md py-1.5 text-sm font-semibold transition"
                                    :class="tamano === '{{ $k }}' ? 'bg-white text-carbon-900 shadow-sm' : 'text-carbon-500 hover:text-carbon-800'">{{ $v }}</button>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="btn-oscuro h-12 w-full text-base" @click="imprimir()">
                    <x-icono n="imprimir" clase="size-5" /> <span x-text="`Imprimir ${total} ${total === 1 ? 'etiqueta' : 'etiquetas'}`"></span>
                </button>
                <p class="text-center text-xs text-carbon-500">Tip: en la ventana de impresión desactiva "Encabezados y pies de página".</p>
            </div>
        </section>
    </div>

    {{-- Vista previa = lo que se imprime --}}
    <section class="hoja-imprimible min-w-0 rounded-xl bg-white p-4 shadow-sm ring-1 ring-carbon-200/70 sm:p-6">
        <div class="no-imprimir mb-4 flex items-center justify-between text-sm">
            <p class="font-display font-bold">Vista previa</p>
            <p class="text-carbon-500" x-show="total" x-text="`${total} ${total === 1 ? 'etiqueta' : 'etiquetas'} · ${Math.ceil(total / ({ pequena: 24, mediana: 12, grande: 6 })[tamano])} hoja(s) aprox.`"></p>
        </div>
        <div x-show="!total" class="no-imprimir grid place-items-center rounded-xl border-2 border-dashed border-carbon-200 px-6 py-20 text-center">
            <div>
                <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-carbon-50 text-carbon-300"><x-icono n="qr" clase="size-8" /></span>
                <p class="mt-3 font-semibold text-carbon-700">Tu hoja está vacía</p>
                <p class="mt-1 text-sm text-carbon-500">Ejemplo: Copla PVC 3/4" gris · Bolsa x 300 · 20 etiquetas.</p>
            </div>
        </div>
        <div class="grid gap-2" :class="columnas" x-show="total">
            <template x-for="e in hoja" :key="e.key">
                <div class="etiqueta-qr flex items-center gap-2 rounded-md border border-dashed border-carbon-300 p-2" :class="tamano === 'grande' ? 'p-3 gap-3' : ''">
                    <img :src="qrs[texto(e.i)]" alt="" class="aspect-square shrink-0" :class="{ pequena: 'w-16', mediana: 'w-24', grande: 'w-36' }[tamano]">
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="font-bold text-carbon-900" :class="tamano === 'pequena' ? 'text-[10px]' : (tamano === 'grande' ? 'text-base' : 'text-xs')" x-text="producto(e.i.producto_id).nombre"></p>
                        <p x-show="producto(e.i.producto_id).color" class="mt-0.5 inline-flex items-center gap-1 font-semibold text-carbon-700" :class="tamano === 'pequena' ? 'text-[9px]' : 'text-[11px]'">
                            <span class="size-2 rounded-full ring-1 ring-black/20" :style="`background:${colorMuestra(producto(e.i.producto_id).color)}`"></span>
                            <span x-text="producto(e.i.producto_id).color"></span>
                        </p>
                        <p class="mt-1 inline-block rounded bg-carbon-900 px-1.5 py-0.5 font-bold text-white" :class="tamano === 'pequena' ? 'text-[9px]' : (tamano === 'grande' ? 'text-sm' : 'text-[11px]')" x-text="banda(e.i)"></p>
                        <p class="mt-0.5 font-mono text-carbon-500" :class="tamano === 'pequena' ? 'text-[8px]' : 'text-[10px]'" x-text="producto(e.i.producto_id).codigo"></p>
                    </div>
                </div>
            </template>
        </div>
    </section>
</div>
</x-layouts.app>
