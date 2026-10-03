<x-layouts.app titulo="Armar viaje">
<x-slot:migas><a href="{{ route('viajes.index') }}" class="hover:text-carbon-800">Viajes</a><x-icono n="derecha" clase="size-3" />Nuevo</x-slot:migas>

<form method="POST" action="{{ route('viajes.store') }}" class="mx-auto max-w-5xl"
      x-data="armarViaje({ pedidos: @js($pedidos), vehiculos: @js($vehiculos), pilotos: @js($pilotos), preseleccion: @js($preseleccion) })"
      :data-confirmar="resumen + '\n\nSe descontará del inventario lo de todos los pedidos.'" :data-titulo="`¿Sale el viaje con ${paradas.length} ${paradas.length === 1 ? 'pedido' : 'pedidos'}?`" data-boton="Sí, salió a ruta">
    @csrf
    <template x-for="id in paradas" :key="'h' + id"><input type="hidden" name="pedidos[]" :value="id"></template>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
        <div class="space-y-5 lg:col-span-3">
            {{-- Camión y piloto --}}
            <section class="tarjeta">
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo"><span class="mr-1.5 text-carbon-400">1</span> Camión y piloto</h3></div>
                <div class="space-y-4 p-4 sm:p-5">
                    <input type="hidden" name="vehiculo_id" :value="vehiculo">
                    <input type="hidden" name="piloto_id" :value="piloto">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <template x-for="v in vehiculos" :key="v.id">
                            <button type="button" @click="!v.viaje && (vehiculo = v.id)" :disabled="!!v.viaje"
                                    class="flex items-center gap-3 rounded-xl p-3 text-left ring-1 transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="vehiculo === v.id ? 'bg-marca-50 ring-2 ring-marca-500' : 'ring-carbon-200 hover:ring-carbon-400'">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-carbon-100 text-carbon-600"><x-icono n="camion" clase="size-5" /></span>
                                <span class="min-w-0 flex-1 leading-tight">
                                    <span class="block text-sm font-semibold" x-text="`${v.codigo} · ${v.nombre}`"></span>
                                    <span class="block text-xs" :class="v.viaje ? 'font-semibold text-amber-700' : 'text-carbon-500'" x-text="v.viaje ? `En ruta (${v.viaje})` : (v.estado !== 'operativa' ? 'En mantenimiento' : v.marca)"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in pilotos" :key="p.id">
                            <button type="button" @click="piloto = p.id" class="flex items-center gap-2 rounded-full py-1.5 pr-4 pl-1.5 text-sm font-semibold ring-1 transition"
                                    :class="piloto === p.id ? 'bg-carbon-900 text-white ring-carbon-900' : 'ring-carbon-200 hover:ring-carbon-400'">
                                <span class="grid size-7 place-items-center rounded-full bg-carbon-100 text-[10px] font-bold text-carbon-700" x-text="p.iniciales"></span>
                                <span x-text="p.nombre"></span>
                                <span x-show="p.viaje" class="text-[11px] font-medium opacity-70" x-text="`· en ${p.viaje}`"></span>
                            </button>
                        </template>
                        <p x-show="!pilotos.length" class="text-sm text-carbon-500">No hay pilotos: márcalos en Usuarios con "Es piloto".</p>
                    </div>
                </div>
            </section>

            {{-- Pedidos listos, por municipio --}}
            <section class="tarjeta">
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo"><span class="mr-1.5 text-carbon-400">2</span> Pedidos listos</h3><span class="text-xs text-carbon-500">Agrupados por municipio</span></div>
                <template x-if="!pedidos.length">
                    <x-vacio icono="camion" titulo="No hay pedidos listos para ruta" texto="Cuando bodega termine de armar un pedido de entrega en ruta, aparecerá aquí." />
                </template>
                <template x-for="[municipio, lista] in grupos" :key="municipio">
                    <div class="border-b border-carbon-100 last:border-0">
                        <div class="flex items-center justify-between bg-carbon-50/70 px-4 py-2">
                            <p class="flex items-center gap-1.5 text-xs font-bold tracking-wide text-carbon-600 uppercase"><x-icono n="ubicacion" clase="size-3.5" /> <span x-text="municipio"></span> <span class="font-medium text-carbon-400" x-text="`· ${lista.length}`"></span></p>
                            <button type="button" x-show="lista.length > 1" class="text-xs font-semibold text-marca-700 hover:underline" @click="todoElGrupo(lista)"
                                    x-text="lista.every(p => elegido(p.id)) ? 'Quitar todos' : 'Llevar todos'"></button>
                        </div>
                        <template x-for="p in lista" :key="p.id">
                            <button type="button" @click="alternar(p.id)" class="flex w-full items-start gap-3 px-4 py-3 text-left transition active:bg-carbon-50" :class="elegido(p.id) && 'bg-marca-50/40'">
                                <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-md text-xs font-bold ring-2 transition"
                                      :class="elegido(p.id) ? 'bg-marca-600 text-white ring-marca-600' : 'bg-white ring-carbon-300'" x-text="elegido(p.id) ? paradas.indexOf(p.id) + 1 : ''"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5 text-sm font-semibold"><x-icono n="rayo" clase="size-3.5 shrink-0 text-marca-600" x-show="p.urgente" /><span class="truncate" x-text="p.cliente"></span></span>
                                    <span class="block truncate text-xs text-carbon-500" x-text="`${p.folio} · ${p.productos} productos · ${p.direccion || ''}`"></span>
                                </span>
                                <span class="shrink-0 text-xs font-semibold" :class="p.atrasado ? 'text-marca-700' : (p.hoy ? 'text-amber-700' : 'text-carbon-500')" x-text="p.atrasado ? 'Atrasado' : (p.hoy ? 'Hoy' : p.fecha)"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </section>
        </div>

        {{-- Recorrido --}}
        <div class="lg:col-span-2">
            <section class="tarjeta lg:sticky lg:top-20">
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo"><span class="mr-1.5 text-carbon-400">3</span> Recorrido</h3><span class="text-xs text-carbon-500" x-text="paradas.length ? `${paradas.length} paradas` : ''"></span></div>
                <p x-show="!paradas.length" class="px-4 py-8 text-center text-sm text-carbon-500">Toca los pedidos que van en este camión.</p>
                <ol class="px-4 py-2" x-show="paradas.length">
                    <template x-for="(id, i) in paradas" :key="id">
                        <li class="relative flex items-center gap-3 py-2">
                            <span class="absolute top-9 bottom-[-0.5rem] left-[0.8125rem] w-px bg-carbon-200" x-show="i < paradas.length - 1"></span>
                            <span class="relative grid size-7 shrink-0 place-items-center rounded-full bg-carbon-900 text-xs font-bold text-white" x-text="i + 1"></span>
                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block truncate text-sm font-semibold" x-text="pedido(id).cliente"></span>
                                <span class="block truncate text-xs text-carbon-500" x-text="pedido(id).municipio"></span>
                            </span>
                            <span class="flex shrink-0">
                                <button type="button" class="btn-icono text-carbon-400 hover:text-carbon-900 disabled:opacity-20" :disabled="i === 0" @click="mover(i, -1)" aria-label="Subir"><x-icono n="abajo" clase="size-4 rotate-180" /></button>
                                <button type="button" class="btn-icono text-carbon-400 hover:text-carbon-900 disabled:opacity-20" :disabled="i === paradas.length - 1" @click="mover(i, 1)" aria-label="Bajar"><x-icono n="abajo" clase="size-4" /></button>
                                <button type="button" class="btn-icono text-carbon-400 hover:text-marca-700" @click="alternar(id)" aria-label="Quitar"><x-icono n="x" clase="size-4" /></button>
                            </span>
                        </li>
                    </template>
                </ol>
                <div class="space-y-3 border-t border-carbon-100 p-4">
                    <input name="notas" class="campo" placeholder="Nota para el piloto (opcional)">
                    @error('pedidos')<p class="error">{{ $message }}</p>@enderror
                    @error('vehiculo_id')<p class="error">{{ $message }}</p>@enderror
                    <button class="btn-primario h-12 w-full text-base disabled:opacity-40" :disabled="!listo">
                        <x-icono n="camion" clase="size-5" />
                        <span x-text="!vehiculo ? 'Elige el camión' : (!piloto ? 'Elige el piloto' : (!paradas.length ? 'Elige los pedidos' : `Salió a ruta · ${paradas.length} ${paradas.length === 1 ? 'pedido' : 'pedidos'}`))"></span>
                    </button>
                    <p x-show="paradas.length" class="text-center text-xs text-carbon-500" x-text="municipios"></p>
                </div>
            </section>
        </div>
    </div>
</form>
</x-layouts.app>
