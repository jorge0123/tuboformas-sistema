<x-layouts.app :titulo="$p->exists ? 'Editar producto' : 'Nuevo producto'">
<x-slot:migas><a href="{{ route('productos.index') }}" class="hover:text-carbon-800">Inventario</a><x-icono n="derecha" clase="size-3" />{{ $p->exists ? $p->codigo : 'Nuevo' }}</x-slot:migas>

@php $pres = old('presentaciones', $p->exists ? $p->presentaciones->map->only(['id', 'nombre', 'factor'])->values()->all() : []); @endphp
<form method="POST" enctype="multipart/form-data" action="{{ $p->exists ? route('productos.update', $p) : route('productos.store') }}" class="mx-auto max-w-4xl space-y-6">
    @csrf
    @if ($p->exists) @method('PUT') @endif
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Producto</h3></div>
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-6">
            <x-campo nombre="codigo" etiqueta="Código" :valor="$p->codigo" requerido clase="sm:col-span-2" placeholder="PT-COP-34" />
            <x-campo nombre="nombre" etiqueta="Nombre" :valor="$p->nombre" requerido clase="sm:col-span-4" placeholder="Copla PVC 3/4&quot;" />
            <x-campo nombre="tipo" etiqueta="Tipo" requerido clase="sm:col-span-2">
                <select id="tipo" name="tipo" class="campo">@foreach (\App\Models\Producto::TIPOS as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $p->tipo) === $k)>{{ $v }}</option>@endforeach</select>
            </x-campo>
            <x-campo nombre="categoria_id" etiqueta="Categoría" clase="sm:col-span-2">
                <select id="categoria_id" name="categoria_id" class="campo"><option value="">Sin categoría</option>@foreach ($categorias as $c)<option value="{{ $c->id }}" @selected(old('categoria_id', $p->categoria_id) == $c->id)>{{ $c->nombre }}</option>@endforeach</select>
            </x-campo>
            <x-campo nombre="medida" etiqueta="Medida" :valor="$p->medida" clase="sm:col-span-1" placeholder="3/4&quot;" />
            <x-campo nombre="color" etiqueta="Color" :valor="$p->color" clase="sm:col-span-1" placeholder="Gris" list="colores" />
            <datalist id="colores"><option value="Gris"><option value="Naranja"><option value="Blanco"><option value="Negro"></datalist>
            <x-campo nombre="unidad_id" etiqueta="Unidad base" requerido clase="sm:col-span-2" ayuda="En lo que se lleva la existencia (pieza, tubo, kg…).">
                <select id="unidad_id" name="unidad_id" class="campo">@foreach ($unidades as $u)<option value="{{ $u->id }}" @selected(old('unidad_id', $p->unidad_id) == $u->id)>{{ $u->nombre }} ({{ $u->abreviatura }})</option>@endforeach</select>
            </x-campo>
            <x-campo nombre="stock_minimo" etiqueta="Stock mínimo" tipo="number" step="any" :valor="$p->stock_minimo" clase="sm:col-span-2" ayuda="Se avisa cuando baja de aquí." />
            <x-campo nombre="ubicacion" etiqueta="Ubicación en bodega" :valor="$p->ubicacion" clase="sm:col-span-2" placeholder="Rack A-3" />
            @if (! $p->exists && auth()->user()->can('inventario.ver_costos'))
                <x-campo nombre="costo_promedio" etiqueta="Costo inicial (Q por unidad)" tipo="number" step="0.0001" clase="sm:col-span-2" ayuda="Después lo calcula el sistema con cada compra." />
            @endif
            <x-campo nombre="foto" etiqueta="Foto" clase="sm:col-span-4">
                <input id="foto" name="foto" type="file" accept="image/*" capture="environment" class="campo py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-carbon-100 file:px-3 file:py-1 file:text-sm file:font-semibold">
            </x-campo>
            <x-campo nombre="descripcion" etiqueta="Descripción" clase="sm:col-span-6"><textarea id="descripcion" name="descripcion" rows="2" class="campo">{{ old('descripcion', $p->descripcion) }}</textarea></x-campo>
            <label class="flex items-center gap-2 text-sm font-medium sm:col-span-6"><input type="checkbox" name="activo" value="1" class="check" @checked(old('activo', $p->activo))> Producto activo</label>
        </div>
    </section>

    <section class="tarjeta" x-data="{ filas: @js($pres) }">
        <div class="tarjeta-cabeza">
            <div><h3 class="tarjeta-titulo">Presentaciones</h3><p class="text-xs text-carbon-500">Cómo se cuenta en físico. Ej. "Bolsa x 300" = 300 piezas, "Manojo" = 32 tubos.</p></div>
            <button type="button" class="btn-secundario btn-sm" @click="filas.push({ id: null, nombre: '', factor: '' })"><x-icono n="mas" clase="size-4" /> Presentación</button>
        </div>
        <div class="space-y-2 p-5">
            <template x-for="(f, i) in filas" :key="i">
                <div class="flex items-center gap-2">
                    <input type="hidden" :name="`presentaciones[${i}][id]`" :value="f.id">
                    <input :name="`presentaciones[${i}][nombre]`" x-model="f.nombre" class="campo flex-1" placeholder="Nombre (ej. Bolsa x 300)">
                    <span class="text-sm text-carbon-500">=</span>
                    <input :name="`presentaciones[${i}][factor]`" x-model="f.factor" type="number" step="any" min="0" class="campo w-32" placeholder="Cantidad">
                    <span class="w-16 text-sm text-carbon-500">unidades</span>
                    <button type="button" class="rounded-md p-2 text-carbon-400 hover:bg-carbon-100 hover:text-marca-700" @click="filas.splice(i, 1)"><x-icono n="x" clase="size-4" /></button>
                </div>
            </template>
            <p x-show="!filas.length" class="text-center text-sm text-carbon-500">Sin presentaciones: se captura directamente en la unidad base.</p>
        </div>
    </section>

    <div class="flex items-center justify-between">
        <div>@if ($p->exists)@can('inventario.gestionar')<button type="submit" form="eliminar" class="btn-peligro"><x-icono n="basura" clase="size-4" /> Eliminar</button>@endcan @endif</div>
        <div class="flex flex-wrap gap-2"><a href="{{ $p->exists ? route('productos.show', $p) : route('productos.index') }}" class="btn-secundario">Cancelar</a><button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar</button></div>
    </div>
</form>
@if ($p->exists)
<form id="eliminar" method="POST" action="{{ route('productos.destroy', $p) }}" data-confirmar="Si tiene movimientos se desactivará en lugar de eliminarse." data-titulo="Eliminar producto" data-boton="Eliminar" data-peligro>@csrf @method('DELETE')</form>
@endif
</x-layouts.app>
