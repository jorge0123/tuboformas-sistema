<x-layouts.app titulo="Ingreso rápido">
<x-slot:migas><a href="{{ route('movimientos.index') }}" class="hover:text-carbon-800">Bodega</a><x-icono n="derecha" clase="size-3" />Ingreso de producto terminado</x-slot:migas>

<div x-data="ingresoRapido({ productos: @js($productos), limpiar: @js((bool) session('ultimo')) })" class="mx-auto max-w-2xl" @keydown.escape.window="cerrarCamara(); propuesta = null; buscando = false">

    {{-- Acciones principales: botones grandes para usar con una mano --}}
    <section class="tarjeta aparecer overflow-hidden">
        <div class="bg-carbon-900 px-5 py-5 text-white">
            <p class="font-display text-lg font-extrabold">Escanea la etiqueta</p>
            <p class="mt-0.5 text-sm text-carbon-300">El sistema propone el producto y la cantidad de la bolsa; tú confirmas o corriges.</p>
            <a href="{{ route('bodega.etiquetas') }}" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-marca-300 hover:text-white"><x-icono n="imprimir" clase="size-3.5" /> ¿Bolsas sin etiqueta? Imprime etiquetas QR</a>
        </div>
        <div class="grid grid-cols-2 gap-3 p-4">
            <button type="button" @click="abrirCamara()" class="btn-primario col-span-2 h-16 text-base active:scale-[.98]">
                <x-icono n="escanear" clase="size-6" /> Escanear QR
            </button>
            <label class="btn-secundario h-14 cursor-pointer active:scale-[.98]" :class="leyendoFoto && 'pointer-events-none opacity-60'">
                <input x-ref="foto" type="file" accept="image/*" capture="environment" class="sr-only" @change="leerFoto">
                <x-icono n="camara" clase="size-5" x-show="!leyendoFoto" />
                <svg x-show="leyendoFoto" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                <span x-text="leyendoFoto ? 'Leyendo…' : 'Tomar foto'"></span>
            </label>
            <button type="button" @click="abrirBusqueda()" class="btn-secundario h-14 active:scale-[.98]">
                <x-icono n="buscar" clase="size-5" /> Buscar
            </button>
        </div>
    </section>

    @if (session('ultimo'))
        @php $ultimo = \App\Models\Movimiento::find(session('ultimo')); @endphp
        @if ($ultimo)
            <a href="{{ route('movimientos.show', $ultimo) }}" class="aparecer mt-4 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm ring-1 ring-emerald-200 transition hover:bg-emerald-100">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-600 text-white"><x-icono n="check" clase="size-5" /></span>
                <span class="min-w-0 flex-1"><b class="text-emerald-900">{{ $ultimo->folio }}</b> <span class="text-emerald-800">registrado. Ya puedes escanear el siguiente lote.</span></span>
                <x-icono n="derecha" clase="size-4 text-emerald-700" />
            </a>
        @endif
    @endif

    {{-- Lo que se va a ingresar --}}
    <form method="POST" action="{{ route('bodega.ingreso-rapido.store') }}" enctype="multipart/form-data"
          :data-confirmar="resumen" :data-titulo="`¿Registrar ${lineas.length} ${lineas.length === 1 ? 'producto' : 'productos'}?`" data-boton="Sí, registrar ingreso">
        @csrf
        <template x-for="(l, i) in lineas" :key="'h' + i">
            <div>
                <input type="hidden" :name="`lineas[${i}][producto_id]`" :value="l.producto_id">
                <input type="hidden" :name="`lineas[${i}][presentacion_id]`" :value="l.presentacion_id ?? ''">
                <input type="hidden" :name="`lineas[${i}][cantidad]`" :value="l.cantidad">
            </div>
        </template>

        <section class="tarjeta mt-4">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Por ingresar</h3>
                <span class="insignia-gris" x-show="lineas.length" x-text="`${lineas.length} ${lineas.length === 1 ? 'línea' : 'líneas'}`"></span>
            </div>

            <div x-show="!lineas.length" class="flex flex-col items-center px-6 py-10 text-center">
                <span class="grid size-16 place-items-center rounded-2xl bg-carbon-50 text-carbon-300 ring-1 ring-carbon-100"><x-icono n="qr" clase="size-8" /></span>
                <p class="mt-3 font-semibold text-carbon-800">Todavía no has escaneado nada</p>
                <p class="mt-1 max-w-xs text-sm text-carbon-500">Escanea el QR de cada bolsa o caja. Si escaneas el mismo producto otra vez, se suma.</p>
            </div>

            <ul class="divide-y divide-carbon-100">
                <template x-for="(l, i) in lineas" :key="l.producto_id + '-' + l.presentacion_id">
                    <li class="flex items-center gap-3 px-4 py-3 transition-colors duration-700" x-data="{ recien: Date.now() - (l.marca || 0) < 1500 }"
                        x-init="recien && setTimeout(() => recien = false, 900)" :class="recien ? 'bg-emerald-50' : 'bg-white'">
                        <span class="size-3 shrink-0 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(describir(l).p.color)}`"></span>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-sm leading-snug font-semibold text-carbon-900" x-text="nombre(describir(l).p)"></p>
                            <p class="text-xs text-carbon-500">
                                <span x-text="describir(l).pres ? describir(l).pres.nombre : describir(l).p.unidad"></span> ·
                                <b class="text-carbon-800" x-text="`${fmt(describir(l).base)} ${describir(l).p.unidad}`"></b>
                            </p>
                        </div>
                        <div class="flex items-center rounded-lg ring-1 ring-carbon-200">
                            <button type="button" class="grid size-9 place-items-center text-carbon-600 active:bg-carbon-100" @click="l.cantidad > 1 ? sumar(l, -1) : quitar(i)" :aria-label="l.cantidad > 1 ? 'Restar uno' : 'Quitar'">
                                <x-icono n="menos" clase="size-4" x-show="l.cantidad > 1" /><x-icono n="basura" clase="size-4 text-marca-600" x-show="l.cantidad <= 1" />
                            </button>
                            <input type="number" inputmode="decimal" min="0" step="any" x-model.number="l.cantidad" class="w-12 border-0 bg-transparent p-0 text-center text-sm font-bold tabular-nums focus:ring-0" aria-label="Cantidad">
                            <button type="button" class="grid size-9 place-items-center text-carbon-600 active:bg-carbon-100" @click="sumar(l, 1)" aria-label="Sumar uno"><x-icono n="mas" clase="size-4" /></button>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

        <section class="tarjeta mt-4" x-show="lineas.length" x-transition>
            <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                <div>
                    <label class="etiqueta" for="bodega_destino_id">Entra a</label>
                    <select id="bodega_destino_id" name="bodega_destino_id" class="campo">
                        @foreach ($bodegas as $b)<option value="{{ $b->id }}" @selected($b->id === $bodegaInicial)>{{ $b->nombre }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="etiqueta" for="referencia">Referencia</label>
                    <input id="referencia" name="referencia" class="campo" placeholder="Mesa 2 · turno A" value="{{ old('referencia') }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="etiqueta" for="fotos">Fotos de evidencia <span class="font-normal text-carbon-400">(opcional)</span></label>
                    <input id="fotos" name="fotos[]" type="file" accept="image/*" capture="environment" multiple
                           class="campo py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-carbon-100 file:px-3 file:py-1 file:text-sm file:font-semibold">
                </div>
            </div>
        </section>

        {{-- Barra fija abajo: siempre a la mano del pulgar --}}
        <div class="sobre-barra sticky bottom-0 z-20 -mx-4 mt-4 border-t border-carbon-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:shadow-lg"
             x-show="lineas.length" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full opacity-0">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="text-xs text-carbon-500">Total a ingresar</p>
                    <p class="font-display text-lg font-extrabold tabular-nums" x-text="fmt(totalBase) + ' unidades'"></p>
                </div>
                <button class="btn-exito h-12 px-5 text-base"><x-icono n="check" clase="size-5" /> Registrar ingreso</button>
            </div>
        </div>
    </form>

    {{-- Cámara en vivo --}}
    <div x-show="camara" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex flex-col bg-black">
        <video x-ref="video" playsinline muted class="absolute inset-0 size-full object-cover"></video>
        <div class="pointer-events-none absolute inset-0 grid place-items-center">
            <div class="relative size-64 rounded-3xl shadow-[0_0_0_9999px_rgba(0,0,0,.55)]">
                @foreach (['left-0 top-0 border-l-4 border-t-4 rounded-tl-3xl', 'right-0 top-0 border-r-4 border-t-4 rounded-tr-3xl', 'bottom-0 left-0 border-b-4 border-l-4 rounded-bl-3xl', 'bottom-0 right-0 border-b-4 border-r-4 rounded-br-3xl'] as $esquina)
                    <span class="absolute size-10 border-white {{ $esquina }}"></span>
                @endforeach
                <span class="absolute inset-x-4 h-0.5 animate-[barrido_2s_ease-in-out_infinite] rounded-full bg-marca-500 shadow-[0_0_12px_2px_rgba(217,0,22,.7)]"></span>
            </div>
        </div>
        <div class="relative mt-auto p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-center">
            <p class="mb-4 text-sm font-medium text-white/90">Apunta al código QR de la bolsa</p>
            <button type="button" class="btn h-12 w-full max-w-xs bg-white/15 text-white ring-1 ring-white/30 backdrop-blur hover:bg-white/25" @click="cerrarCamara()">Cancelar</button>
        </div>
    </div>

    {{-- Hoja inferior: búsqueda manual --}}
    <div x-show="buscando" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-4">
        <div x-show="buscando" x-transition.opacity class="absolute inset-0 bg-carbon-950/50 backdrop-blur-[2px]" @click="buscando = false"></div>
        <div x-show="buscando" x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-full sm:opacity-0"
             class="relative flex max-h-[85vh] w-full flex-col rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl">
            <div class="mx-auto mt-2.5 h-1.5 w-10 rounded-full bg-carbon-200 sm:hidden"></div>
            <div class="p-4">
                <div class="relative">
                    <x-icono n="buscar" clase="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-carbon-400" />
                    <input x-ref="buscar" x-model="q" type="search" class="campo h-11 pl-9" placeholder="Copla 3/4, naranja, PT-COP…">
                </div>
            </div>
            <ul class="scroll-fino flex-1 overflow-y-auto border-t border-carbon-100">
                <template x-for="p in resultados" :key="p.id">
                    <li>
                        <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-carbon-50 active:bg-carbon-100" @click="proponer(p)">
                            <span class="size-3 shrink-0 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(p.color)}`"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold" x-text="nombre(p)"></span>
                                <span class="block font-mono text-xs text-carbon-500" x-text="p.codigo"></span>
                            </span>
                            <x-icono n="derecha" clase="size-4 text-carbon-300" />
                        </button>
                    </li>
                </template>
                <li x-show="!resultados.length" class="px-4 py-8 text-center text-sm text-carbon-500">Sin coincidencias.</li>
            </ul>
        </div>
    </div>

    {{-- Hoja inferior: "¿Deseas registrar…?" --}}
    <div x-show="propuesta" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-4">
        <div x-show="propuesta" x-transition.opacity class="absolute inset-0 bg-carbon-950/50 backdrop-blur-[2px]" @click="propuesta = null"></div>
        <template x-if="propuesta">
            <div x-data="{ get p() { return this.producto(this.propuesta.producto_id) }, get d() { return this.describir(this.propuesta) } }"
                 x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative w-full rounded-t-2xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:max-w-md sm:rounded-2xl">
                <div class="mx-auto -mt-2 mb-3 h-1.5 w-10 rounded-full bg-carbon-200 sm:hidden"></div>
                <p class="text-xs font-semibold tracking-wide text-carbon-500 uppercase">¿Deseas registrar?</p>
                <div class="mt-1 flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-display text-xl leading-tight font-extrabold text-carbon-900" x-text="p.nombre"></h3>
                        <p class="mt-0.5 font-mono text-xs text-carbon-500" x-text="p.codigo"></p>
                    </div>
                    <span x-show="p.color" class="insignia-gris shrink-0"><span class="size-2.5 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(p.color)}`"></span><span x-text="p.color"></span></span>
                </div>

                {{-- Variantes: mismo producto en otro color --}}
                <div x-show="variantes(p).length > 1" class="mt-4">
                    <p class="etiqueta">Color</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="v in variantes(p)" :key="v.id">
                            <button type="button" @click="cambiarVariante(v)"
                                    class="flex h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 transition active:scale-95"
                                    :class="v.id === p.id ? 'bg-carbon-900 text-white ring-carbon-900' : 'bg-white text-carbon-700 ring-carbon-200 hover:ring-carbon-400'">
                                <span class="size-3 rounded-full ring-1 ring-black/10" :style="`background:${colorMuestra(v.color)}`"></span>
                                <span x-text="v.color || 'Sin color'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Presentación --}}
                <div class="mt-4">
                    <p class="etiqueta">Se cuenta por</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="pr in p.presentaciones" :key="pr.id">
                            <button type="button" @click="propuesta.presentacion_id = pr.id"
                                    class="h-10 rounded-lg px-3 text-sm font-semibold ring-1 transition active:scale-95"
                                    :class="propuesta.presentacion_id === pr.id ? 'bg-marca-600 text-white ring-marca-600' : 'bg-white text-carbon-700 ring-carbon-200'" x-text="pr.nombre"></button>
                        </template>
                        <button type="button" @click="propuesta.presentacion_id = null"
                                class="h-10 rounded-lg px-3 text-sm font-semibold ring-1 transition active:scale-95"
                                :class="propuesta.presentacion_id === null ? 'bg-marca-600 text-white ring-marca-600' : 'bg-white text-carbon-700 ring-carbon-200'" x-text="'Suelto (' + p.unidad + ')'"></button>
                    </div>
                </div>

                {{-- Cantidad --}}
                <div class="mt-4 flex items-center gap-4">
                    <div class="flex items-center rounded-xl ring-1 ring-carbon-200">
                        <button type="button" class="grid size-12 place-items-center text-carbon-700 active:bg-carbon-100" @click="sumar(propuesta, -1)" aria-label="Menos"><x-icono n="menos" /></button>
                        <input type="number" inputmode="decimal" min="0" step="any" x-model.number="propuesta.cantidad" @focus="$el.select()"
                               class="w-16 border-0 bg-transparent p-0 text-center font-display text-2xl font-extrabold tabular-nums focus:ring-0" aria-label="Cantidad">
                        <button type="button" class="grid size-12 place-items-center text-carbon-700 active:bg-carbon-100" @click="sumar(propuesta, 1)" aria-label="Más"><x-icono n="mas" /></button>
                    </div>
                    <div class="leading-tight">
                        <p class="text-xs text-carbon-500" x-text="d.pres ? `${fmt(propuesta.cantidad)} × ${d.pres.nombre}` : 'Unidades sueltas'"></p>
                        <p class="font-display text-2xl font-extrabold text-emerald-700 tabular-nums" x-text="`= ${fmt(d.base)} ${p.unidad}`"></p>
                    </div>
                </div>

                <button type="button" class="btn-exito mt-5 h-14 w-full text-base" @click="aceptar()">
                    <x-icono n="check" clase="size-5" /> <span x-text="`Sí, ${fmt(d.base)} ${p.unidad}`"></span>
                </button>
                <div class="mt-2 flex gap-2">
                    <button type="button" class="btn-fantasma h-11 flex-1" @click="propuesta = null">Cancelar</button>
                    <button type="button" class="btn-fantasma h-11 flex-1" @click="abrirBusqueda()"><x-icono n="buscar" clase="size-4" /> Otro producto</button>
                </div>
            </div>
        </template>
    </div>
</div>
</x-layouts.app>
