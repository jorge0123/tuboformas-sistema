<x-layouts.app :titulo="$u->exists ? 'Editar usuario' : 'Nuevo usuario'">
<x-slot:migas><a href="{{ route('usuarios.index') }}" class="hover:text-carbon-800">Usuarios</a><x-icono n="derecha" clase="size-3" />{{ $u->exists ? $u->username : 'Nuevo' }}</x-slot:migas>
@php
    $rolActual = old('rol', $u->rolPrincipal()?->name ?? 'tecnico');
    $extra = old('permisos_extra', $u->exists ? $u->permissions->pluck('name')->all() : []);
@endphp

<form method="POST" action="{{ $u->exists ? route('usuarios.update', $u) : route('usuarios.store') }}" class="mx-auto grid max-w-6xl grid-cols-1 gap-6 lg:grid-cols-5"
      x-data="{ rol: @js($rolActual), delRol: @js($permisosRol), extra: @js($extra),
                tiene(p) { return (this.delRol[this.rol] || []).includes(p) },
                toggle(p) { this.extra.includes(p) ? this.extra.splice(this.extra.indexOf(p), 1) : this.extra.push(p) } }">
    @csrf
    @if ($u->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Datos personales</h3></div>
            <div class="space-y-4 p-5">
                <x-campo nombre="name" etiqueta="Nombre completo" :valor="$u->name" requerido />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="username" etiqueta="Usuario" :valor="$u->username" requerido placeholder="jperez" ayuda="Con esto inicia sesión." />
                    <x-campo nombre="telefono" etiqueta="Teléfono" :valor="$u->telefono" />
                </div>
                <x-campo nombre="email" etiqueta="Correo" tipo="email" :valor="$u->email" ayuda="Opcional. Necesario para recibir avisos por correo." />
                <x-campo nombre="puesto" etiqueta="Puesto" :valor="$u->puesto" />
                <x-campo nombre="especialidad_id" etiqueta="Especialidad" ayuda="Para técnicos: mecánico, eléctrico… Sirve para asignar y filtrar órdenes.">
                    <select id="especialidad_id" name="especialidad_id" class="campo"><option value="">Ninguna</option>@foreach ($especialidades as $e)<option value="{{ $e->id }}" @selected(old('especialidad_id', $u->especialidad_id) == $e->id)>{{ $e->nombre }}</option>@endforeach</select>
                </x-campo>
            </div>
        </section>
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Acceso</h3></div>
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="password" etiqueta="{{ $u->exists ? 'Nueva contraseña' : 'Contraseña' }}" tipo="password" :requerido="! $u->exists" autocomplete="new-password" ayuda="{{ $u->exists ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8, con letras y números.' }}" />
                    <x-campo nombre="password_confirmation" etiqueta="Confirmar" tipo="password" autocomplete="new-password" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" class="check" @checked(old('activo', $u->activo))> Usuario activo</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notif_email" value="1" class="check" @checked(old('notif_email', $u->notif_email))> Recibir avisos por correo</label>
            </div>
        </section>
    </div>

    <div class="space-y-6 lg:col-span-3">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Rol</h3><p class="text-xs text-carbon-500">Define la base de lo que puede hacer.</p></div></div>
            <div class="grid grid-cols-1 gap-2 p-5 sm:grid-cols-2">
                @foreach ($roles as $r)
                    <label class="flex cursor-pointer gap-3 rounded-xl p-3 ring-1 transition" :class="rol === '{{ $r->name }}' ? 'bg-marca-50/60 ring-marca-400' : 'ring-carbon-200 hover:bg-carbon-50'">
                        <input type="radio" name="rol" value="{{ $r->name }}" x-model="rol" class="mt-0.5 size-4 border-carbon-300 text-marca-600 focus:ring-marca-500">
                        <span class="min-w-0"><span class="block text-sm font-semibold">{{ $r->nombre ?? $r->name }}</span><span class="block text-xs text-carbon-500">{{ $r->descripcion }}</span></span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <div><h3 class="tarjeta-titulo">Permisos adicionales</h3><p class="text-xs text-carbon-500">Además de su rol. Útil para el contador o un usuario con acceso especial.</p></div>
                <span class="insignia-violeta" x-show="extra.filter(p => !tiene(p)).length" x-text="'+' + extra.filter(p => !tiene(p)).length"></span>
            </div>
            <div class="divide-y divide-carbon-100">
                @foreach (\App\Support\Permisos::modulos() as $clave => [$nombre, $permisos])
                    <div x-data="{ abierto: false }">
                        <button type="button" class="flex w-full items-center justify-between px-5 py-3 text-left hover:bg-carbon-50" @click="abierto = !abierto">
                            <span class="text-sm font-semibold">{{ $nombre }}</span>
                            <span class="flex items-center gap-2 text-xs text-carbon-500">
                                <span x-text="{{ json_encode(array_keys($permisos)) }}.filter(p => tiene(p) || extra.includes(p)).length + '/{{ count($permisos) }}'"></span>
                                <x-icono n="abajo" clase="size-4 transition" ::class="abierto && 'rotate-180'" />
                            </span>
                        </button>
                        <div x-show="abierto" x-collapse class="space-y-1 px-5 pb-3">
                            @foreach ($permisos as $p => $desc)
                                <label class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-sm" :class="tiene('{{ $p }}') ? 'text-carbon-400' : 'hover:bg-carbon-50 cursor-pointer'">
                                    <span class="flex items-center gap-2">
                                        <input type="checkbox" class="check" :checked="tiene('{{ $p }}') || extra.includes('{{ $p }}')" :disabled="tiene('{{ $p }}')" @change="toggle('{{ $p }}')">
                                        {{ $desc }}
                                    </span>
                                    <span x-show="tiene('{{ $p }}')" class="insignia-gris text-[10px]">Por su rol</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <template x-for="p in extra.filter(p => !tiene(p))" :key="p"><input type="hidden" name="permisos_extra[]" :value="p"></template>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="btn-secundario">Cancelar</a>
            <button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar usuario</button>
        </div>
    </div>
</form>
</x-layouts.app>
