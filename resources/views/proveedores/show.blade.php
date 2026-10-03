<x-layouts.app :titulo="$proveedor->nombre">
<x-slot:migas><a href="{{ route('proveedores.index') }}" class="hover:text-carbon-800">Proveedores</a><x-icono n="derecha" clase="size-3" />{{ $proveedor->nombre }}</x-slot:migas>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="tarjeta self-start p-5">
        <div class="flex items-center gap-3">
            <span class="grid size-12 place-items-center rounded-xl bg-marca-50 font-display font-extrabold text-marca-700">{{ mb_strtoupper(mb_substr($proveedor->nombre, 0, 2)) }}</span>
            <div><h2 class="font-display text-lg font-extrabold">{{ $proveedor->nombre }}</h2><p class="text-xs text-carbon-500">{{ $proveedor->nit ? 'NIT '.$proveedor->nit : 'Sin NIT' }}</p></div>
        </div>
        <div class="mt-3 flex flex-wrap gap-1">
            @unless ($proveedor->activo)<span class="insignia-roja">Inactivo</span>@endunless
            @foreach ($proveedor->tipos ?? [] as $t)<span class="insignia-gris">{{ \App\Models\Proveedor::TIPOS[$t] ?? $t }}</span>@endforeach
        </div>
        <dl class="mt-5 space-y-3 text-sm">
            @foreach (['usuario' => $proveedor->contacto, 'telefono' => $proveedor->telefono, 'correo' => $proveedor->email, 'ubicacion' => $proveedor->direccion] as $i => $v)
                @if ($v)<div class="flex items-start gap-2.5"><x-icono :n="$i" clase="mt-0.5 size-4 shrink-0 text-carbon-400" /><span>{{ $v }}</span></div>@endif
            @endforeach
        </dl>
        @if ($proveedor->notas)<p class="mt-4 rounded-lg bg-carbon-50 p-3 text-sm text-carbon-600">{{ $proveedor->notas }}</p>@endif
        @can('proveedores.gestionar')<a href="{{ route('proveedores.edit', $proveedor) }}" class="btn-secundario mt-5 w-full"><x-icono n="lapiz" clase="size-4" /> Editar</a>@endcan
    </section>

    <div class="space-y-6 lg:col-span-2">
        <section class="tarjeta overflow-hidden">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Catálogo de productos y repuestos</h3></div>
            @can('proveedores.gestionar')
            @if ($disponibles->isNotEmpty())
            <form method="POST" action="{{ route('proveedores.productos.store', $proveedor) }}" class="grid grid-cols-2 gap-2 border-b border-carbon-100 bg-carbon-50/50 p-4 sm:grid-cols-6">
                @csrf
                <select name="producto_id" required class="campo col-span-2 sm:col-span-3"><option value="">Agregar producto…</option>@foreach ($disponibles as $d)<option value="{{ $d->id }}">{{ $d->codigo }} · {{ $d->nombre }}</option>@endforeach</select>
                <input name="codigo_proveedor" class="campo" placeholder="Su código">
                @can('inventario.ver_costos')<input name="precio" type="number" step="0.01" min="0" class="campo" placeholder="Precio Q">@endcan
                <div class="flex flex-wrap gap-2"><input name="dias_entrega" type="number" min="0" class="campo" placeholder="Días"><button class="btn-oscuro btn-icono" aria-label="Agregar"><x-icono n="mas" clase="size-4" /></button></div>
            </form>
            @endif
            @endcan
            @if ($proveedor->productos->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-carbon-500">Aún no hay productos vinculados.</p>
            @else
            <table class="tabla">
                <thead><tr><th>Producto</th><th>Código proveedor</th>@can('inventario.ver_costos')<th class="text-right">Precio</th>@endcan<th class="text-right">Entrega</th><th></th></tr></thead>
                <tbody class="divide-y divide-carbon-50">
                @foreach ($proveedor->productos as $pr)
                    <tr>
                        <td><a href="{{ route('productos.show', $pr) }}" class="font-semibold text-carbon-900 hover:text-marca-700">{{ $pr->nombre }}</a><p class="font-mono text-xs text-carbon-500">{{ $pr->codigo }}</p></td>
                        <td>{{ $pr->pivot->codigo_proveedor ?? '—' }}</td>
                        @can('inventario.ver_costos')<td class="tabla-num">@dinero($pr->pivot->precio)</td>@endcan
                        <td class="tabla-num">{{ $pr->pivot->dias_entrega !== null ? $pr->pivot->dias_entrega.' días' : '—' }}</td>
                        <td class="text-right">
                            @can('proveedores.gestionar')
                            <form method="POST" action="{{ route('proveedores.productos.destroy', [$proveedor, $pr]) }}" data-confirmar="Se quitará del catálogo de este proveedor." data-titulo="Quitar producto" data-boton="Quitar">@csrf @method('DELETE')
                                <button class="btn-fantasma btn-sm text-marca-700"><x-icono n="x" clase="size-4" /></button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </section>

        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Servicios registrados en bitácora</h3></div>
            @forelse ($servicios as $s)
                <a href="{{ route('maquinas.show', ['maquina' => $s->maquina_id, 'tab' => 'bitacora']) }}" class="flex items-center gap-4 border-b border-carbon-50 px-5 py-3 transition last:border-0 hover:bg-carbon-50">
                    <span class="w-20 shrink-0 text-xs font-semibold text-carbon-500">@fecha($s->fecha)</span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $s->trabajo_realizado }}</p><p class="truncate text-xs text-carbon-500">{{ $s->maquina->etiqueta() }}</p></div>
                    @if ($s->garantia)<span class="insignia-ambar">Garantía</span>@endif
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-carbon-500">Sin servicios registrados.</p>
            @endforelse
        </section>
    </div>
</div>
</x-layouts.app>
