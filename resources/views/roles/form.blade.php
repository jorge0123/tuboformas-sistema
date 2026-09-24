<x-layouts.app :titulo="$rol->exists ? 'Rol: '.($rol->nombre ?? $rol->name) : 'Nuevo rol'">
<x-slot:migas><a href="{{ route('roles.index') }}" class="hover:text-carbon-800">Roles y permisos</a><x-icono n="derecha" clase="size-3" />{{ $rol->exists ? $rol->nombre : 'Nuevo' }}</x-slot:migas>

<form method="POST" action="{{ $rol->exists ? route('roles.update', $rol) : route('roles.store') }}" class="mx-auto max-w-4xl space-y-6"
      x-data="{ marcados: @js(old('permisos', $marcados)),
                todos(lista) { return lista.every(p => this.marcados.includes(p)) },
                alternar(lista) { const t = this.todos(lista); lista.forEach(p => { const i = this.marcados.indexOf(p); if (t && i > -1) this.marcados.splice(i, 1); if (!t && i === -1) this.marcados.push(p) }) } }">
    @csrf
    @if ($rol->exists) @method('PUT') @endif
    <section class="tarjeta">
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-3">
            <x-campo nombre="nombre" etiqueta="Nombre del rol" :valor="$rol->nombre" requerido placeholder="ej. Auditor externo" />
            <x-campo nombre="descripcion" etiqueta="Descripción" :valor="$rol->descripcion" clase="sm:col-span-2" placeholder="Para quién es y qué hace" />
        </div>
    </section>

    <section class="tarjeta overflow-hidden">
        <div class="tarjeta-cabeza">
            <h3 class="tarjeta-titulo">Permisos por módulo</h3>
            <span class="text-sm text-carbon-500"><b x-text="marcados.length" class="text-carbon-900"></b> de {{ count(\App\Support\Permisos::todos()) }}</span>
        </div>
        <div class="divide-y divide-carbon-100">
            @foreach (\App\Support\Permisos::modulos() as $clave => [$nombre, $permisos])
                @php $lista = array_keys($permisos); @endphp
                <div class="grid grid-cols-1 gap-3 p-5 md:grid-cols-4">
                    <div>
                        <p class="font-display font-bold">{{ $nombre }}</p>
                        <button type="button" class="mt-1 text-xs font-semibold text-marca-700 hover:underline" @click="alternar(@js($lista))" x-text="todos(@js($lista)) ? 'Quitar todos' : 'Marcar todos'"></button>
                    </div>
                    <div class="grid grid-cols-1 gap-1 sm:grid-cols-2 md:col-span-3">
                        @foreach ($permisos as $p => $desc)
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-carbon-50">
                                <input type="checkbox" name="permisos[]" value="{{ $p }}" x-model="marcados" class="check mt-0.5">
                                <span>{{ $desc }}<span class="block font-mono text-[10px] text-carbon-400">{{ $p }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex items-center justify-between">
        <div>@if ($rol->exists && ! $rol->es_sistema)<button type="submit" form="eliminar" class="btn-peligro"><x-icono n="basura" clase="size-4" /> Eliminar rol</button>@endif</div>
        <div class="flex gap-2"><a href="{{ route('roles.index') }}" class="btn-secundario">Cancelar</a><button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar rol</button></div>
    </div>
</form>
@if ($rol->exists && ! $rol->es_sistema)
<form id="eliminar" method="POST" action="{{ route('roles.destroy', $rol) }}" data-confirmar="Solo se puede eliminar si no tiene usuarios." data-titulo="Eliminar rol" data-boton="Eliminar" data-peligro>@csrf @method('DELETE')</form>
@endif
</x-layouts.app>
