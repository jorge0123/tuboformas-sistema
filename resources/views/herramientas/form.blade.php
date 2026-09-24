<x-layouts.app :titulo="$h->exists ? 'Editar herramienta' : 'Nueva herramienta'">
<x-slot:migas><a href="{{ route('herramientas.index') }}" class="hover:text-carbon-800">Herramientas</a><x-icono n="derecha" clase="size-3" />{{ $h->exists ? $h->codigo : 'Nueva' }}</x-slot:migas>

<form method="POST" enctype="multipart/form-data" action="{{ $h->exists ? route('herramientas.update', $h) : route('herramientas.store') }}" class="mx-auto max-w-3xl space-y-6">
    @csrf
    @if ($h->exists) @method('PUT') @endif
    <section class="tarjeta">
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-6">
            <x-campo nombre="codigo" etiqueta="Código" :valor="$h->codigo" requerido clase="sm:col-span-2" placeholder="HER-009" />
            <x-campo nombre="nombre" etiqueta="Nombre" :valor="$h->nombre" requerido clase="sm:col-span-4" />
            <x-campo nombre="categoria" etiqueta="Categoría" :valor="$h->categoria" clase="sm:col-span-2" list="categorias" placeholder="Manual, Eléctrica, Medición…" />
            <datalist id="categorias">@foreach ($categorias as $c)<option value="{{ $c }}">@endforeach</datalist>
            <x-campo nombre="marca" etiqueta="Marca" :valor="$h->marca" clase="sm:col-span-2" />
            <x-campo nombre="modelo" etiqueta="Modelo" :valor="$h->modelo" clase="sm:col-span-2" />
            <x-campo nombre="serie" etiqueta="Serie" :valor="$h->serie" clase="sm:col-span-2" />
            <x-campo nombre="costo" etiqueta="Costo (Q)" tipo="number" step="0.01" :valor="$h->costo" clase="sm:col-span-2" />
            <x-campo nombre="fecha_compra" etiqueta="Fecha de compra" tipo="date" :valor="$h->fecha_compra?->format('Y-m-d')" clase="sm:col-span-2" />
            @if ($h->estado !== 'asignada')
            <x-campo nombre="estado" etiqueta="Estado" clase="sm:col-span-2">
                <select id="estado" name="estado" class="campo">
                    @foreach (['disponible', 'en_reparacion', 'perdida', 'baja'] as $k)<option value="{{ $k }}" @selected(old('estado', $h->estado) === $k)>{{ \App\Models\Herramienta::ESTADOS[$k] }}</option>@endforeach
                </select>
            </x-campo>
            @endif
            <x-campo nombre="foto" etiqueta="Foto" clase="sm:col-span-4">
                <input id="foto" name="foto" type="file" accept="image/*" capture="environment" class="campo py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-carbon-100 file:px-3 file:py-1 file:text-sm file:font-semibold">
            </x-campo>
            <x-campo nombre="notas" etiqueta="Notas" clase="sm:col-span-6"><textarea id="notas" name="notas" rows="2" class="campo">{{ old('notas', $h->notas) }}</textarea></x-campo>
        </div>
    </section>
    <div class="flex items-center justify-between">
        <div>@if ($h->exists)<button type="submit" form="eliminar" class="btn-peligro"><x-icono n="basura" clase="size-4" /> Eliminar</button>@endif</div>
        <div class="flex gap-2"><a href="{{ $h->exists ? route('herramientas.show', $h) : route('herramientas.index') }}" class="btn-secundario">Cancelar</a><button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar</button></div>
    </div>
</form>
@if ($h->exists)
<form id="eliminar" method="POST" action="{{ route('herramientas.destroy', $h) }}" data-confirmar="Solo se elimina si nunca se ha entregado; si tiene historial, márcala como De baja." data-titulo="Eliminar herramienta" data-boton="Eliminar" data-peligro>@csrf @method('DELETE')</form>
@endif
</x-layouts.app>
