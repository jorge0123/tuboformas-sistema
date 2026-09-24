<x-layouts.app :titulo="$plan->exists ? 'Editar plan preventivo' : 'Nuevo plan preventivo'">
<x-slot:migas><a href="{{ route('planes.index') }}" class="hover:text-carbon-800">Planes preventivos</a><x-icono n="derecha" clase="size-3" />{{ $plan->exists ? $plan->titulo : 'Nuevo' }}</x-slot:migas>

@php $checklist = old('checklist', $plan->checklist ?? []); @endphp
<form method="POST" action="{{ $plan->exists ? route('planes.update', $plan) : route('planes.store') }}" class="mx-auto grid max-w-5xl grid-cols-1 gap-6 lg:grid-cols-3"
      x-data="{ valor: {{ old('frecuencia_valor', $plan->frecuencia_valor) }}, unidad: '{{ old('frecuencia_unidad', $plan->frecuencia_unidad) }}', fecha: '{{ old('proxima_fecha', $plan->proxima_fecha?->format('Y-m-d')) }}', anticipacion: {{ old('dias_anticipacion', $plan->dias_anticipacion) }} }">
    @csrf
    @if ($plan->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Tarea que se repite</h3></div>
            <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
                <x-campo nombre="titulo" etiqueta="Título" :valor="$plan->titulo" requerido clase="sm:col-span-2" placeholder="ej. Lubricación de prensa" />
                <x-campo nombre="maquina_id" etiqueta="Máquina" clase="sm:col-span-2">
                    <select id="maquina_id" name="maquina_id" class="campo">
                        <option value="">General (sin máquina)</option>
                        @foreach ($maquinas as $m)<option value="{{ $m->id }}" @selected(old('maquina_id', $plan->maquina_id) == $m->id)>{{ $m->codigo ? $m->codigo.' · ' : '' }}{{ $m->nombre }}</option>@endforeach
                    </select>
                </x-campo>
                <x-campo nombre="descripcion" etiqueta="Instrucciones" clase="sm:col-span-2">
                    <textarea id="descripcion" name="descripcion" rows="3" class="campo">{{ old('descripcion', $plan->descripcion) }}</textarea>
                </x-campo>
            </div>
        </section>
        <section class="tarjeta" x-data="filas(@js(array_map(fn ($t) => ['texto' => $t], array_values($checklist))), { texto: '' })">
            <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Lista de verificación</h3><p class="text-xs text-carbon-500">Se copia a cada orden que genere el plan.</p></div></div>
            <div class="space-y-2 p-5">
                <template x-for="(f, i) in filas" :key="i">
                    <div class="flex items-center gap-2">
                        <span class="grid size-6 shrink-0 place-items-center rounded-md bg-carbon-100 text-xs font-bold text-carbon-500" x-text="i + 1"></span>
                        <input name="checklist[]" x-model="f.texto" class="campo" placeholder="ej. Engrasar guías y bujes" @keydown.enter.prevent="agregar()">
                        <button type="button" class="rounded-md p-2 text-carbon-400 hover:bg-carbon-100 hover:text-marca-700" @click="quitar(i)"><x-icono n="x" clase="size-4" /></button>
                    </div>
                </template>
                <button type="button" class="text-sm font-semibold text-marca-700 hover:underline" @click="agregar()">+ Agregar paso</button>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Programación</h3></div>
            <div class="space-y-4 p-5">
                <div>
                    <span class="etiqueta">Se repite cada</span>
                    <div class="flex gap-2">
                        <input name="frecuencia_valor" type="number" min="1" max="365" x-model="valor" class="campo w-20" required>
                        <select name="frecuencia_unidad" x-model="unidad" class="campo flex-1">
                            @foreach (\App\Models\PlanMantenimiento::UNIDADES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <x-campo nombre="proxima_fecha" etiqueta="Próxima fecha" requerido>
                    <input id="proxima_fecha" name="proxima_fecha" type="date" x-model="fecha" class="campo" required>
                </x-campo>
                <x-campo nombre="dias_anticipacion" etiqueta="Crear la orden con anticipación de" ayuda="Días antes de la fecha en que aparece la orden al técnico.">
                    <div class="flex items-center gap-2"><input id="dias_anticipacion" name="dias_anticipacion" type="number" min="0" max="60" x-model="anticipacion" class="campo w-20"><span class="text-sm text-carbon-600">días</span></div>
                </x-campo>
                <p class="rounded-lg bg-violet-50 px-3 py-2.5 text-xs text-violet-900 ring-1 ring-violet-200" x-show="fecha">
                    <x-icono n="calendario" clase="mr-1 inline size-3.5" />
                    La orden se creará el <b x-text="(() => { const d = new Date(fecha + 'T00:00'); d.setDate(d.getDate() - anticipacion); return d.toLocaleDateString('es-GT') })()"></b>
                    con vencimiento el <b x-text="new Date(fecha + 'T00:00').toLocaleDateString('es-GT')"></b>, y después cada <span x-text="valor"></span> <span x-text="{ dias: 'días', semanas: 'semanas', meses: 'meses' }[unidad]"></span>.
                </p>
                <x-campo nombre="duracion_estimada" etiqueta="Duración estimada (h)" tipo="number" step="0.25" :valor="$plan->duracion_estimada" />
            </div>
        </section>
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Asignación</h3></div>
            <div class="space-y-4 p-5">
                <x-campo nombre="tipo" etiqueta="Tipo">
                    <select id="tipo" name="tipo" class="campo">
                        <option value="preventivo" @selected(old('tipo', $plan->tipo) === 'preventivo')>Preventivo</option>
                        <option value="predictivo" @selected(old('tipo', $plan->tipo) === 'predictivo')>Predictivo</option>
                    </select>
                </x-campo>
                <x-campo nombre="especialidad_id" etiqueta="Especialidad">
                    <select id="especialidad_id" name="especialidad_id" class="campo">
                        <option value="">Cualquiera</option>
                        @foreach ($especialidades as $e)<option value="{{ $e->id }}" @selected(old('especialidad_id', $plan->especialidad_id) == $e->id)>{{ $e->nombre }}</option>@endforeach
                    </select>
                </x-campo>
                <x-campo nombre="responsable_id" etiqueta="Responsable">
                    <select id="responsable_id" name="responsable_id" class="campo">
                        <option value="">Asignar después</option>
                        @foreach ($tecnicos as $t)<option value="{{ $t->id }}" @selected(old('responsable_id', $plan->responsable_id) == $t->id)>{{ $t->name }}{{ $t->especialidad ? ' · '.$t->especialidad->nombre : '' }}</option>@endforeach
                    </select>
                </x-campo>
                <x-campo nombre="prioridad" etiqueta="Prioridad">
                    <select id="prioridad" name="prioridad" class="campo">
                        @foreach (\App\Models\OrdenTrabajo::PRIORIDADES as $k => $v)<option value="{{ $k }}" @selected(old('prioridad', $plan->prioridad) === $k)>{{ $v }}</option>@endforeach
                    </select>
                </x-campo>
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="activo" value="1" class="check" @checked(old('activo', $plan->activo))> Plan activo</label>
            </div>
        </section>
        <div class="flex gap-2">
            @if ($plan->exists)<button type="submit" form="eliminar" class="btn-peligro"><x-icono n="basura" clase="size-4" /></button>@endif
            <a href="{{ route('planes.index') }}" class="btn-secundario flex-1">Cancelar</a>
            <button class="btn-primario flex-[2]"><x-icono n="check" clase="size-4" /> Guardar plan</button>
        </div>
    </div>
</form>
@if ($plan->exists)
<form id="eliminar" method="POST" action="{{ route('planes.destroy', $plan) }}" data-confirmar="Las órdenes que ya generó se conservan." data-titulo="Eliminar plan" data-boton="Eliminar" data-peligro>@csrf @method('DELETE')</form>
@endif
</x-layouts.app>
