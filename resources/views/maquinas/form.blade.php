<x-layouts.app :titulo="$maquina->exists ? 'Editar máquina' : 'Nueva máquina'">
<x-slot:migas><a href="{{ route('maquinas.index') }}" class="hover:text-carbon-800">Máquinas</a><x-icono n="derecha" clase="size-3" />{{ $maquina->exists ? $maquina->nombre : 'Nueva' }}</x-slot:migas>

@php
    $componentes = old('componentes', $maquina->componentes?->map(fn ($c) => ['nombre' => $c->nombre, 'specs' => $c->especificaciones ?: [['clave' => '', 'valor' => '']]])->values()->all() ?? []);
    $partes = old('partes', $maquina->partes?->map->only(['grupo', 'especificacion', 'dimensiones', 'cantidad', 'producto_id'])->values()->all() ?? []);
@endphp

<form method="POST" enctype="multipart/form-data" action="{{ $maquina->exists ? route('maquinas.update', $maquina) : route('maquinas.store') }}" class="mx-auto max-w-5xl space-y-6">
    @csrf
    @if ($maquina->exists) @method('PUT') @endif

    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Datos generales</h3></div>
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-6">
            <x-campo nombre="numero" etiqueta="No." tipo="number" :valor="$maquina->numero" clase="sm:col-span-1" />
            <x-campo nombre="codigo" etiqueta="Código / designación" :valor="$maquina->codigo" clase="sm:col-span-2" placeholder="ej. INY1" ayuda="Como en el listado: AF1, CA1, BTF…" />
            <x-campo nombre="nombre" etiqueta="Descripción" :valor="$maquina->nombre" requerido clase="sm:col-span-3" placeholder="ej. Inyectora 1" />
            <x-campo nombre="area_id" etiqueta="Área" clase="sm:col-span-2">
                <select id="area_id" name="area_id" class="campo">
                    <option value="">Sin área</option>
                    @foreach ($areas as $a)<option value="{{ $a->id }}" @selected(old('area_id', $maquina->area_id) == $a->id)>{{ $a->nombre }}</option>@endforeach
                </select>
            </x-campo>
            <x-campo nombre="marca" etiqueta="Marca" :valor="$maquina->marca" clase="sm:col-span-2" />
            <x-campo nombre="modelo" etiqueta="Modelo" :valor="$maquina->modelo" clase="sm:col-span-2" />
            <x-campo nombre="serie" etiqueta="No. de serie" :valor="$maquina->serie" clase="sm:col-span-2" />
            <x-campo nombre="anio" etiqueta="Año de fabricación" tipo="number" :valor="$maquina->anio" clase="sm:col-span-2" />
            <x-campo nombre="ubicacion" etiqueta="Ubicación" :valor="$maquina->ubicacion" clase="sm:col-span-2" />
            <x-campo nombre="estado" etiqueta="Estado" requerido clase="sm:col-span-2">
                <select id="estado" name="estado" class="campo">
                    @foreach (\App\Models\Maquina::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(old('estado', $maquina->estado) === $k)>{{ $v }}</option>@endforeach
                </select>
            </x-campo>
            <x-campo nombre="criticidad" etiqueta="Criticidad" requerido clase="sm:col-span-2">
                <select id="criticidad" name="criticidad" class="campo">
                    @foreach (\App\Models\Maquina::CRITICIDADES as $k => $v)<option value="{{ $k }}" @selected(old('criticidad', $maquina->criticidad) === $k)>{{ $v }}</option>@endforeach
                </select>
            </x-campo>
            <x-campo nombre="horometro" etiqueta="Horómetro (h)" tipo="number" step="0.1" :valor="$maquina->horometro" clase="sm:col-span-2" />
            <x-campo nombre="observaciones" etiqueta="Observaciones" clase="sm:col-span-4">
                <textarea id="observaciones" name="observaciones" rows="2" class="campo" placeholder="Voltaje, capacidad, notas…">{{ old('observaciones', $maquina->observaciones) }}</textarea>
            </x-campo>
            <div class="sm:col-span-2" x-data="{ vista: @js($maquina->foto ? Storage::url($maquina->foto) : null) }">
                <span class="etiqueta">Foto</span>
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-carbon-300 p-2 transition hover:border-marca-400 hover:bg-marca-50/40">
                    <template x-if="vista"><img :src="vista" class="size-14 rounded-md object-cover"></template>
                    <template x-if="!vista"><span class="grid size-14 place-items-center rounded-md bg-carbon-100 text-carbon-400"><x-icono n="camara" /></span></template>
                    <span class="text-sm text-carbon-600">Tomar o elegir foto</span>
                    <input type="file" name="foto" accept="image/*" class="sr-only" @change="vista = URL.createObjectURL($event.target.files[0])">
                </label>
            </div>
        </div>
    </section>

    {{-- Ficha técnica --}}
    <section id="ficha" class="tarjeta" x-data="{ comps: @js($componentes) }">
        <div class="tarjeta-cabeza">
            <div>
                <h3 class="tarjeta-titulo">Ficha técnica</h3>
                <p class="text-xs text-carbon-500">Un bloque por componente (Inyectora, Motor eléctrico, Bomba…) con los datos de su placa.</p>
            </div>
            <button type="button" class="btn-secundario btn-sm" @click="comps.push({ nombre: '', specs: [{ clave: '', valor: '' }] })"><x-icono n="mas" clase="size-4" /> Componente</button>
        </div>
        <div class="space-y-4 p-5">
            <template x-for="(c, i) in comps" :key="i">
                <div class="rounded-xl ring-1 ring-carbon-200">
                    <div class="flex items-center gap-2 border-b border-carbon-100 bg-carbon-50/60 px-4 py-2.5">
                        <x-icono n="engrane" clase="size-4 text-marca-600" />
                        <input :name="`componentes[${i}][nombre]`" x-model="c.nombre" class="campo max-w-xs py-1.5 font-semibold" placeholder="Nombre del componente">
                        <button type="button" class="btn-fantasma btn-sm ml-auto text-marca-700" @click="comps.splice(i, 1)"><x-icono n="basura" clase="size-4" /> Quitar</button>
                    </div>
                    <div class="grid grid-cols-1 gap-2 p-4 md:grid-cols-2">
                        <template x-for="(s, j) in c.specs" :key="j">
                            <div class="flex items-center gap-2">
                                <input :name="`componentes[${i}][specs][${j}][clave]`" x-model="s.clave" class="campo w-2/5 py-1.5" placeholder="Dato (ej. Voltaje)">
                                <input :name="`componentes[${i}][specs][${j}][valor]`" x-model="s.valor" class="campo flex-1 py-1.5" placeholder="Valor (ej. 460 Vac)">
                                <button type="button" class="rounded-md p-1.5 text-carbon-400 hover:bg-carbon-100 hover:text-marca-700" @click="c.specs.splice(j, 1)" aria-label="Quitar dato"><x-icono n="x" clase="size-4" /></button>
                            </div>
                        </template>
                        <button type="button" class="justify-self-start text-sm font-semibold text-marca-700 hover:underline" @click="c.specs.push({ clave: '', valor: '' })">+ Agregar dato</button>
                    </div>
                </div>
            </template>
            <p x-show="!comps.length" class="rounded-xl border border-dashed border-carbon-200 py-8 text-center text-sm text-carbon-500">Sin componentes. Usa “Componente” para agregar el primero.</p>
        </div>
    </section>

    {{-- Partes --}}
    <section class="tarjeta" x-data="{ partes: @js($partes) }">
        <div class="tarjeta-cabeza">
            <div>
                <h3 class="tarjeta-titulo">Partes y consumibles</h3>
                <p class="text-xs text-carbon-500">Ej. lista de resistencias, filtros, fajas. Vincúlalas al repuesto de bodega para ver su existencia.</p>
            </div>
            <button type="button" class="btn-secundario btn-sm" @click="partes.push({ grupo: '', especificacion: '', dimensiones: '', cantidad: 1, producto_id: '' })"><x-icono n="mas" clase="size-4" /> Parte</button>
        </div>
        <div class="overflow-x-auto p-5" x-show="partes.length">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-carbon-500 uppercase"><th class="pb-2">Grupo</th><th class="pb-2">Especificación</th><th class="pb-2">Dimensiones</th><th class="w-24 pb-2">Cant.</th><th class="pb-2">Repuesto en bodega</th><th></th></tr></thead>
                <tbody>
                <template x-for="(p, i) in partes" :key="i">
                    <tr>
                        <td class="pr-2 pb-2"><input :name="`partes[${i}][grupo]`" x-model="p.grupo" class="campo py-1.5" placeholder="Resistencias"></td>
                        <td class="pr-2 pb-2"><input :name="`partes[${i}][especificacion]`" x-model="p.especificacion" class="campo py-1.5" placeholder="240-480V 2350W"></td>
                        <td class="pr-2 pb-2"><input :name="`partes[${i}][dimensiones]`" x-model="p.dimensiones" class="campo py-1.5" placeholder="ø150 x 138 mm"></td>
                        <td class="pr-2 pb-2"><input :name="`partes[${i}][cantidad]`" x-model="p.cantidad" type="number" step="any" class="campo py-1.5"></td>
                        <td class="pr-2 pb-2">
                            <select :name="`partes[${i}][producto_id]`" x-model="p.producto_id" class="campo py-1.5">
                                <option value="">Sin vincular</option>
                                @foreach ($repuestos as $r)<option value="{{ $r->id }}">{{ $r->codigo }} · {{ $r->nombre }}</option>@endforeach
                            </select>
                        </td>
                        <td class="pb-2"><button type="button" class="rounded-md p-1.5 text-carbon-400 hover:bg-carbon-100 hover:text-marca-700" @click="partes.splice(i, 1)"><x-icono n="x" clase="size-4" /></button></td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
        <p x-show="!partes.length" class="p-5 text-center text-sm text-carbon-500">Sin partes registradas.</p>
    </section>

    <div class="flex items-center justify-between gap-3">
        <div>
            @if ($maquina->exists)
                @can('maquinas.eliminar')
                <button type="submit" form="eliminar" class="btn-peligro"><x-icono n="basura" clase="size-4" /> Eliminar</button>
                @endcan
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ $maquina->exists ? route('maquinas.show', $maquina) : route('maquinas.index') }}" class="btn-secundario">Cancelar</a>
            <button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar máquina</button>
        </div>
    </div>
</form>
@if ($maquina->exists)
<form id="eliminar" method="POST" action="{{ route('maquinas.destroy', $maquina) }}" data-confirmar="Solo se puede eliminar si no tiene órdenes ni bitácora. Si tiene historial, márcala como “De baja”." data-titulo="Eliminar máquina" data-boton="Eliminar" data-peligro>
    @csrf @method('DELETE')
</form>
@endif
</x-layouts.app>
