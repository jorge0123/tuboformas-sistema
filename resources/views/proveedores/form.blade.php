<x-layouts.app :titulo="$p->exists ? 'Editar proveedor' : 'Nuevo proveedor'">
<x-slot:migas><a href="{{ route('proveedores.index') }}" class="hover:text-carbon-800">Proveedores</a><x-icono n="derecha" clase="size-3" />{{ $p->exists ? $p->nombre : 'Nuevo' }}</x-slot:migas>

<form method="POST" action="{{ $p->exists ? route('proveedores.update', $p) : route('proveedores.store') }}" class="mx-auto max-w-3xl space-y-6">
    @csrf
    @if ($p->exists) @method('PUT') @endif
    <section class="tarjeta">
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-6">
            <x-campo nombre="nombre" etiqueta="Nombre o razón social" :valor="$p->nombre" requerido clase="sm:col-span-4" />
            <x-campo nombre="nit" etiqueta="NIT" :valor="$p->nit" clase="sm:col-span-2" />
            <x-campo nombre="contacto" etiqueta="Contacto" :valor="$p->contacto" clase="sm:col-span-2" />
            <x-campo nombre="telefono" etiqueta="Teléfono" :valor="$p->telefono" clase="sm:col-span-2" />
            <x-campo nombre="email" etiqueta="Correo" tipo="email" :valor="$p->email" clase="sm:col-span-2" />
            <x-campo nombre="direccion" etiqueta="Dirección" :valor="$p->direccion" clase="sm:col-span-6" />
            <div class="sm:col-span-6">
                <span class="etiqueta">¿Qué nos provee?</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Proveedor::TIPOS as $k => $v)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-carbon-200 transition has-checked:bg-marca-50 has-checked:ring-marca-300">
                            <input type="checkbox" name="tipos[]" value="{{ $k }}" class="check" @checked(in_array($k, old('tipos', $p->tipos ?? [])))> {{ $v }}
                        </label>
                    @endforeach
                </div>
            </div>
            <x-campo nombre="notas" etiqueta="Notas" clase="sm:col-span-6"><textarea id="notas" name="notas" rows="2" class="campo">{{ old('notas', $p->notas) }}</textarea></x-campo>
            <label class="flex items-center gap-2 text-sm font-medium sm:col-span-6"><input type="checkbox" name="activo" value="1" class="check" @checked(old('activo', $p->activo))> Proveedor activo</label>
        </div>
    </section>
    <div class="flex items-center justify-between">
        <div>@if ($p->exists)<button type="submit" form="eliminar" class="btn-peligro"><x-icono n="basura" clase="size-4" /> Eliminar</button>@endif</div>
        <div class="flex flex-wrap gap-2"><a href="{{ $p->exists ? route('proveedores.show', $p) : route('proveedores.index') }}" class="btn-secundario">Cancelar</a><button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar</button></div>
    </div>
</form>
@if ($p->exists)
<form id="eliminar" method="POST" action="{{ route('proveedores.destroy', $p) }}" data-confirmar="Si tiene historial se desactivará en lugar de eliminarse." data-titulo="Eliminar proveedor" data-boton="Eliminar" data-peligro>@csrf @method('DELETE')</form>
@endif
</x-layouts.app>
