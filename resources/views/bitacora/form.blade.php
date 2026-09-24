<x-layouts.app :titulo="$b->exists ? 'Editar registro de bitácora' : 'Registrar en bitácora'">
<x-slot:migas><a href="{{ route('bitacora.index') }}" class="hover:text-carbon-800">Bitácora</a><x-icono n="derecha" clase="size-3" />{{ $b->exists ? 'Editar' : 'Nuevo' }}</x-slot:migas>

<form method="POST" action="{{ $b->exists ? route('bitacora.update', $b) : route('bitacora.store') }}" class="mx-auto max-w-3xl space-y-6"
      x-data="{ externo: {{ old('proveedor_id', $b->proveedor_id) ? 'true' : 'false' }}, historico: {{ old('responsable_nombre', $b->responsable_nombre) ? 'true' : 'false' }} }">
    @csrf
    @if ($b->exists) @method('PUT') @endif

    @unless ($b->exists)
    <p class="flex items-start gap-2 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-200">
        <x-icono n="libro" clase="mt-0.5 size-4 shrink-0" />
        Los trabajos hechos con una orden de trabajo llegan solos a la bitácora al completarse. Usa este formulario para trabajos sin orden o para cargar el historial del Excel.
    </p>
    @endunless

    <section class="tarjeta">
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-6">
            <x-campo nombre="maquina_id" etiqueta="Máquina" requerido clase="sm:col-span-4">
                <select id="maquina_id" name="maquina_id" class="campo" required>
                    <option value="">Elige…</option>
                    @foreach ($maquinas as $m)<option value="{{ $m->id }}" @selected(old('maquina_id', $b->maquina_id) == $m->id)>{{ $m->codigo ? $m->codigo.' · ' : '' }}{{ $m->nombre }}</option>@endforeach
                </select>
            </x-campo>
            <x-campo nombre="fecha" etiqueta="Fecha terminado" tipo="date" :valor="$b->fecha?->format('Y-m-d')" requerido clase="sm:col-span-2" />
            <x-campo nombre="tipo" etiqueta="Tipo" requerido clase="sm:col-span-2">
                <select id="tipo" name="tipo" class="campo">
                    @foreach (\App\Models\OrdenTrabajo::TIPOS as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $b->tipo) === $k)>{{ $v }}</option>@endforeach
                </select>
            </x-campo>
            <x-campo nombre="componente" etiqueta="Componente" :valor="$b->componente" clase="sm:col-span-2" placeholder="ej. Bloque central de prensa" />
            <x-campo nombre="horas" etiqueta="Horas" tipo="number" step="0.25" :valor="$b->horas" clase="sm:col-span-1" />
            <x-campo nombre="horometro" etiqueta="Horómetro" tipo="number" step="0.1" :valor="$b->horometro" clase="sm:col-span-1" />
            <x-campo nombre="trabajo_realizado" etiqueta="Mantenimiento ejecutado" requerido clase="sm:col-span-6">
                <textarea id="trabajo_realizado" name="trabajo_realizado" rows="3" required class="campo" placeholder="ej. Cambio de manguera puerto X14">{{ old('trabajo_realizado', $b->trabajo_realizado) }}</textarea>
            </x-campo>
            <div class="sm:col-span-6">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="etiqueta mb-0">Responsable</span>
                    <label class="flex items-center gap-2 text-xs text-carbon-600"><input type="checkbox" class="check" x-model="historico"> Ya no está en el sistema (histórico)</label>
                </div>
                <select name="responsable_id" class="campo" x-show="!historico" :disabled="historico">
                    <option value="">Sin responsable</option>
                    @foreach ($usuarios as $u)<option value="{{ $u->id }}" @selected(old('responsable_id', $b->responsable_id) == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
                <input name="responsable_nombre" value="{{ old('responsable_nombre', $b->responsable_nombre) }}" class="campo" placeholder="Nombre del técnico" x-show="historico" x-cloak :disabled="!historico">
            </div>
            <div class="rounded-xl bg-carbon-50 p-4 sm:col-span-6">
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" class="check" x-model="externo"> Lo hizo o participó un proveedor externo</label>
                <div x-show="externo" x-collapse class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <select name="proveedor_id" class="campo sm:col-span-2" :disabled="!externo">
                        <option value="">Proveedor…</option>
                        @foreach ($proveedores as $p)<option value="{{ $p->id }}" @selected(old('proveedor_id', $b->proveedor_id) == $p->id)>{{ $p->nombre }}</option>@endforeach
                    </select>
                    <input name="costo" type="number" step="0.01" min="0" value="{{ old('costo', $b->costo) }}" class="campo" placeholder="Costo Q" :disabled="!externo">
                    <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="garantia" value="1" class="check" @checked(old('garantia', $b->garantia))> Reclamo por garantía</label>
                </div>
            </div>
            <x-campo nombre="comentarios" etiqueta="Comentarios" clase="sm:col-span-6">
                <textarea id="comentarios" name="comentarios" rows="2" class="campo">{{ old('comentarios', $b->comentarios) }}</textarea>
            </x-campo>
        </div>
    </section>

    <div class="flex items-center justify-between gap-2">
        <div>
            @if ($b->exists)
                @can('bitacora.eliminar')<button type="submit" form="eliminar" class="btn-peligro"><x-icono n="basura" clase="size-4" /> Eliminar</button>@endcan
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ url()->previous() }}" class="btn-secundario">Cancelar</a>
            <button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar</button>
        </div>
    </div>
</form>
@if ($b->exists)
<form id="eliminar" method="POST" action="{{ route('bitacora.destroy', $b) }}" data-confirmar="El registro se eliminará de la bitácora de la máquina." data-titulo="Eliminar registro" data-boton="Eliminar" data-peligro>@csrf @method('DELETE')</form>
@endif
</x-layouts.app>
