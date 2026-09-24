<x-layouts.app titulo="Roles y permisos">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="max-w-3xl text-sm text-carbon-600">Cada usuario tiene un rol y, si hace falta, permisos adicionales sueltos. Mecánicos, electricistas y demás son el mismo rol <b>Técnico</b>; lo que cambia es su especialidad.</p>
    <a href="{{ route('roles.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nuevo rol</a>
</div>
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($roles as $r)
        @php $pct = $r->name === 'super_admin' ? 100 : round($r->permissions_count / max(1, $total) * 100); @endphp
        <div class="tarjeta flex flex-col p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl {{ $r->name === 'super_admin' ? 'bg-marca-600 text-white' : 'bg-carbon-100 text-carbon-700' }}"><x-icono n="escudo" clase="size-5" /></span>
                    <div>
                        <p class="font-display font-bold">{{ $r->nombre ?? $r->name }}</p>
                        <p class="text-xs text-carbon-500">{{ $r->users_count }} {{ $r->users_count === 1 ? 'usuario' : 'usuarios' }}{{ $r->es_sistema ? '' : ' · Personalizado' }}</p>
                    </div>
                </div>
                @if ($r->name !== 'super_admin')
                    <a href="{{ route('roles.edit', $r) }}" class="btn-fantasma btn-sm"><x-icono n="lapiz" clase="size-4" /></a>
                @endif
            </div>
            <p class="mt-3 flex-1 text-sm text-carbon-600">{{ $r->descripcion }}</p>
            <div class="mt-4">
                <div class="mb-1 flex justify-between text-xs"><span class="text-carbon-500">Permisos</span><span class="font-semibold">{{ $r->name === 'super_admin' ? 'Todos' : $r->permissions_count.' de '.$total }}</span></div>
                <div class="barra"><span style="width: {{ $pct }}%"></span></div>
            </div>
            <div class="mt-4 flex gap-2 border-t border-carbon-100 pt-3">
                <a href="{{ route('usuarios.index', ['rol' => $r->name]) }}" class="text-xs font-semibold text-carbon-600 hover:text-carbon-900">Ver usuarios</a>
                @if ($r->name !== 'super_admin')<a href="{{ route('roles.create', ['copiar' => $r->name]) }}" class="text-xs font-semibold text-marca-700 hover:underline">Duplicar</a>@endif
            </div>
        </div>
    @endforeach
</div>
</x-layouts.app>
