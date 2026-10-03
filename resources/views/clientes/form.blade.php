<x-layouts.app :titulo="$c->exists ? 'Editar cliente' : 'Nuevo cliente'">
<x-slot:migas><a href="{{ route('clientes.index') }}" class="hover:text-carbon-800">Clientes</a><x-icono n="derecha" clase="size-3" />{{ $c->exists ? $c->nombre : 'Nuevo' }}</x-slot:migas>

<form method="POST" action="{{ $c->exists ? route('clientes.update', $c) : route('clientes.store') }}" class="mx-auto max-w-3xl space-y-6">
    @csrf
    @if ($c->exists) @method('PUT') @endif
    <section class="tarjeta">
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-6">
            <x-campo nombre="nombre" etiqueta="Nombre o razón social" :valor="$c->nombre" requerido clase="sm:col-span-4" />
            <x-campo nombre="nit" etiqueta="NIT" :valor="$c->nit" clase="sm:col-span-2" />
            <x-campo nombre="contacto" etiqueta="Contacto" :valor="$c->contacto" clase="sm:col-span-2" />
            <x-campo nombre="telefono" etiqueta="Teléfono" :valor="$c->telefono" clase="sm:col-span-2" />
            <x-campo nombre="email" etiqueta="Correo" tipo="email" :valor="$c->email" clase="sm:col-span-2" />
            <x-campo nombre="direccion" etiqueta="Dirección de entrega" :valor="$c->direccion" clase="sm:col-span-4" />
            <x-campo nombre="municipio" etiqueta="Municipio" :valor="$c->municipio" clase="sm:col-span-2" />
            <x-campo nombre="notas" etiqueta="Notas" clase="sm:col-span-6"><textarea id="notas" name="notas" rows="2" class="campo">{{ old('notas', $c->notas) }}</textarea></x-campo>
            <label class="flex items-center gap-2 text-sm font-medium sm:col-span-6"><input type="checkbox" name="activo" value="1" class="check" @checked(old('activo', $c->activo))> Cliente activo</label>
        </div>
    </section>
    <div class="flex items-center justify-between">
        <div></div>
        <div class="flex flex-wrap gap-2"><a href="{{ $c->exists ? route('clientes.show', $c) : route('clientes.index') }}" class="btn-secundario">Cancelar</a><button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar</button></div>
    </div>
</form>
</x-layouts.app>
