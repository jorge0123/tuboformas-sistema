<x-layouts.app titulo="Nuevo conteo físico">
<x-slot:migas><a href="{{ route('conteos.index') }}" class="hover:text-carbon-800">Conteos físicos</a><x-icono n="derecha" clase="size-3" />Nuevo</x-slot:migas>
<form method="POST" action="{{ route('conteos.store') }}" class="mx-auto max-w-2xl space-y-6">
    @csrf
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">¿Qué se va a contar?</h3></div>
        <div class="space-y-5 p-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo nombre="tipo" etiqueta="Tipo">
                    <select id="tipo" name="tipo" class="campo"><option value="">Todos</option>@foreach (\App\Models\Producto::TIPOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                </x-campo>
                <x-campo nombre="categoria_id" etiqueta="Categoría">
                    <select id="categoria_id" name="categoria_id" class="campo"><option value="">Todas</option>@foreach ($categorias as $c)<option value="{{ $c->id }}">{{ $c->nombre }}</option>@endforeach</select>
                </x-campo>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="solo_con_existencia" value="1" class="check" checked> Solo repuestos que el sistema dice que hay</label>
            <x-campo nombre="notas" etiqueta="Notas"><textarea id="notas" name="notas" rows="2" class="campo" placeholder="ej. Conteo mensual de rodamientos"></textarea></x-campo>
        </div>
    </section>
    <div class="flex justify-end gap-2"><a href="{{ route('conteos.index') }}" class="btn-secundario">Cancelar</a><button class="btn-primario"><x-icono n="conteo" clase="size-4" /> Abrir conteo</button></div>
</form>
</x-layouts.app>
