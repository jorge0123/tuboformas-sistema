<x-layouts.app titulo="Proveedores">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('proveedores.index')" placeholder="Nombre, NIT o contacto">
        <select name="tipo" class="campo w-auto">
            <option value="">Todo tipo</option>
            @foreach (\App\Models\Proveedor::TIPOS as $k => $v)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="estado" class="campo w-auto">
            <option value="activos">Activos</option>
            <option value="todos" @selected(request('estado') === 'todos')>Incluir inactivos</option>
        </select>
    </x-filtros>
    @can('proveedores.gestionar')<a href="{{ route('proveedores.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nuevo proveedor</a>@endcan
</div>

@if ($proveedores->isEmpty())
    <div class="tarjeta"><x-vacio icono="camion" titulo="Sin proveedores" texto="Registra proveedores de repuestos, servicios técnicos y materia prima." /></div>
@else
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($proveedores as $p)
        <a href="{{ route('proveedores.show', $p) }}" class="tarjeta group flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-md {{ $p->activo ? '' : 'opacity-60' }}">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-marca-50 font-display text-sm font-extrabold text-marca-700">{{ mb_strtoupper(mb_substr($p->nombre, 0, 2)) }}</span>
                <div class="min-w-0">
                    <p class="truncate font-display font-bold text-carbon-900 group-hover:text-marca-700">{{ $p->nombre }}</p>
                    <p class="text-xs text-carbon-500">{{ $p->nit ? 'NIT '.$p->nit : 'Sin NIT' }}{{ $p->activo ? '' : ' · Inactivo' }}</p>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-1">
                @foreach ($p->tipos ?? [] as $t)<span class="insignia-gris">{{ \App\Models\Proveedor::TIPOS[$t] ?? $t }}</span>@endforeach
            </div>
            <div class="mt-4 space-y-1 text-sm text-carbon-600">
                @if ($p->contacto)<p class="flex items-center gap-2"><x-icono n="usuario" clase="size-4 text-carbon-400" />{{ $p->contacto }}</p>@endif
                @if ($p->telefono)<p class="flex items-center gap-2"><x-icono n="telefono" clase="size-4 text-carbon-400" />{{ $p->telefono }}</p>@endif
                @if ($p->email)<p class="flex items-center gap-2 truncate"><x-icono n="correo" clase="size-4 text-carbon-400" />{{ $p->email }}</p>@endif
            </div>
            <div class="mt-auto flex gap-4 border-t border-carbon-100 pt-3 text-xs text-carbon-500" style="margin-top: 1rem">
                <span><b class="text-carbon-800">{{ $p->productos_count }}</b> productos</span>
                <span><b class="text-carbon-800">{{ $p->bitacoras_count }}</b> servicios en bitácora</span>
            </div>
        </a>
    @endforeach
</div>
<div class="tarjeta mt-4 overflow-hidden">{{ $proveedores->links() }}</div>
@endif
</x-layouts.app>
