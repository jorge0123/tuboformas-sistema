<x-layouts.app titulo="Clientes">
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('clientes.index')" placeholder="Nombre, NIT, contacto o municipio">
        <select name="estado" class="campo w-auto">
            <option value="activos">Activos</option>
            <option value="todos" @selected(request('estado') === 'todos')>Todos</option>
        </select>
    </x-filtros>
    @can('clientes.gestionar')<a href="{{ route('clientes.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Cliente</a>@endcan
</div>

<div class="tarjeta overflow-hidden">
    @if ($clientes->isEmpty())
        <x-vacio icono="usuarios" titulo="Sin clientes" texto="Registra un cliente aquí o directamente al ingresar un pedido." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Cliente</th><th>Contacto</th><th>Teléfono</th><th class="text-right">En curso</th><th class="text-right">Pedidos</th><th>Último pedido</th></tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($clientes as $c)
                <tr class="cursor-pointer" onclick="location='{{ route('clientes.show', $c) }}'">
                    <td>
                        <p class="font-semibold text-carbon-900">{{ $c->nombre }} @unless ($c->activo)<span class="insignia-gris">Inactivo</span>@endunless</p>
                        <p class="text-xs text-carbon-500">{{ collect([$c->nit ? 'NIT '.$c->nit : null, $c->municipio])->filter()->join(' · ') ?: '—' }}</p>
                    </td>
                    <td>{{ $c->contacto }}</td>
                    <td class="whitespace-nowrap">{{ $c->telefono }}</td>
                    <td class="tabla-num">@if ($c->activos)<span class="insignia-azul">{{ $c->activos }}</span>@endif</td>
                    <td class="tabla-num">{{ $c->pedidos_count ?: '' }}</td>
                    <td class="text-sm text-carbon-500">{{ $c->pedidos_max_created_at ? \Illuminate\Support\Carbon::parse($c->pedidos_max_created_at)->diffForHumans() : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $clientes->links() }}
    @endif
</div>
</x-layouts.app>
