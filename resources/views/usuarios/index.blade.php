<x-layouts.app titulo="Usuarios">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('usuarios.index')" placeholder="Nombre, usuario o correo">
        <select name="rol" class="campo w-auto">
            <option value="">Todos los roles</option>
            @foreach ($roles as $r)<option value="{{ $r->name }}" @selected(request('rol') === $r->name)>{{ $r->nombre ?? $r->name }}</option>@endforeach
        </select>
        <select name="estado" class="campo w-auto">
            <option value="activos">Activos</option>
            <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
            <option value="todos" @selected(request('estado') === 'todos')>Todos</option>
        </select>
    </x-filtros>
    @can('usuarios.gestionar')<a href="{{ route('usuarios.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nuevo usuario</a>@endcan
</div>
<div class="tarjeta overflow-hidden">
    <div class="overflow-x-auto">
    <table class="tabla">
        <thead><tr><th>Usuario</th><th>Rol</th><th>Especialidad / puesto</th><th>Correo</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead>
        <tbody class="divide-y divide-carbon-50">
        @foreach ($usuarios as $u)
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-full {{ $u->activo ? 'bg-carbon-900 text-white' : 'bg-carbon-200 text-carbon-500' }} text-xs font-bold">{{ $u->iniciales() }}</span>
                        <div><p class="font-semibold text-carbon-900">{{ $u->name }}</p><p class="text-xs text-carbon-500">{{ '@'.$u->username }}</p></div>
                    </div>
                </td>
                <td>
                    <span class="{{ $u->esSuperAdmin() ? 'insignia-roja' : 'insignia-gris' }}">{{ $u->rolPrincipal()?->nombre ?? 'Sin rol' }}</span>
                    @if ($u->permissions->isNotEmpty())<span class="insignia-violeta ml-1" title="{{ $u->permissions->pluck('name')->join(', ') }}">+{{ $u->permissions->count() }} permisos</span>@endif
                </td>
                <td class="text-sm">{{ $u->especialidad?->nombre ?? $u->puesto ?? '—' }}</td>
                <td class="text-sm">{{ $u->email ?? '—' }}</td>
                <td class="text-sm text-carbon-500">{{ $u->ultimo_acceso_at?->diffForHumans() ?? 'Nunca' }}</td>
                <td>@if ($u->activo)<span class="insignia-verde">Activo</span>@else<span class="insignia-gris">Inactivo</span>@endif</td>
                <td class="text-right">
                    @can('usuarios.gestionar')
                        @if (! $u->esSuperAdmin() || auth()->user()->esSuperAdmin())
                            <a href="{{ route('usuarios.edit', $u) }}" class="btn-fantasma btn-sm"><x-icono n="lapiz" clase="size-4" /></a>
                        @endif
                    @endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    {{ $usuarios->links() }}
</div>
</x-layouts.app>
