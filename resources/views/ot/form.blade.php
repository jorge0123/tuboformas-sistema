<x-layouts.app :titulo="$ot->exists ? 'Editar '.$ot->folio : 'Nueva orden de trabajo'">
<x-slot:migas><a href="{{ route('ot.index') }}" class="hover:text-carbon-800">Órdenes de trabajo</a><x-icono n="derecha" clase="size-3" />{{ $ot->exists ? $ot->folio : 'Nueva' }}</x-slot:migas>

@php
    $puedeAsignar = auth()->user()->can('ot.asignar');
    $checklist = old('checklist', $ot->checklist ?? []);
    $ayudantes = old('ayudantes', $ot->exists ? $ot->ayudantes->pluck('id')->all() : []);
@endphp

<form method="POST" action="{{ $ot->exists ? route('ot.update', $ot) : route('ot.store') }}" class="mx-auto grid max-w-6xl grid-cols-1 gap-6 lg:grid-cols-3"
      x-data="{ especialidad: '{{ old('especialidad_id', $ot->especialidad_id) }}', responsable: '{{ old('responsable_id', $ot->responsable_id) }}' }">
    @csrf
    @if ($ot->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">¿Qué hay que hacer?</h3></div>
            <div class="space-y-5 p-5">
                <x-campo nombre="titulo" etiqueta="Título" :valor="$ot->titulo" requerido placeholder="ej. Revisión y limpieza de válvulas del sistema de cierre" autofocus />
                <x-campo nombre="maquina_id" etiqueta="Máquina" ayuda="Déjalo vacío si es una tarea general (instalaciones, proyectos…).">
                    <select id="maquina_id" name="maquina_id" class="campo">
                        <option value="">Tarea general (sin máquina)</option>
                        @foreach ($maquinas as $m)<option value="{{ $m->id }}" @selected(old('maquina_id', $ot->maquina_id) == $m->id)>{{ $m->codigo ? $m->codigo.' · ' : '' }}{{ $m->nombre }}</option>@endforeach
                    </select>
                </x-campo>
                <x-campo nombre="descripcion" etiqueta="Descripción">
                    <textarea id="descripcion" name="descripcion" rows="4" class="campo" placeholder="Falla observada, instrucciones, materiales necesarios…">{{ old('descripcion', $ot->descripcion) }}</textarea>
                </x-campo>
            </div>
        </section>

        <section class="tarjeta" x-data="filas(@js(array_values($checklist)), { texto: '', hecho: false })">
            <div class="tarjeta-cabeza">
                <div><h3 class="tarjeta-titulo">Lista de verificación</h3><p class="text-xs text-carbon-500">Opcional. El técnico marca cada paso al hacerlo.</p></div>
            </div>
            <div class="space-y-2 p-5">
                <template x-for="(f, i) in filas" :key="i">
                    <div class="flex items-center gap-2">
                        <span class="grid size-6 shrink-0 place-items-center rounded-md bg-carbon-100 text-xs font-bold text-carbon-500" x-text="i + 1"></span>
                        <input :name="`checklist[${i}][texto]`" x-model="f.texto" class="campo" placeholder="ej. Revisar nivel de aceite" @keydown.enter.prevent="agregar(); $nextTick(() => $el.closest('section').querySelectorAll('input[type=text],input:not([type])')[i + 1]?.focus())">
                        <input type="hidden" :name="`checklist[${i}][hecho]`" :value="f.hecho ? 1 : 0">
                        <button type="button" class="rounded-md p-2 text-carbon-400 hover:bg-carbon-100 hover:text-marca-700" @click="quitar(i)"><x-icono n="x" clase="size-4" /></button>
                    </div>
                </template>
                <button type="button" class="text-sm font-semibold text-marca-700 hover:underline" @click="agregar()">+ Agregar paso</button>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Clasificación</h3></div>
            <div class="space-y-4 p-5">
                <x-campo nombre="tipo" etiqueta="Tipo" requerido>
                    <select id="tipo" name="tipo" class="campo">
                        @foreach (\App\Models\OrdenTrabajo::TIPOS as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $ot->tipo) === $k)>{{ $v }}</option>@endforeach
                    </select>
                </x-campo>
                <div>
                    <span class="etiqueta">Prioridad <span class="text-marca-600">*</span></span>
                    <div class="grid grid-cols-4 gap-1 rounded-lg bg-carbon-100 p-1" x-data="{ p: '{{ old('prioridad', $ot->prioridad) }}' }">
                        @foreach (['baja' => 'bg-carbon-400', 'media' => 'bg-sky-500', 'alta' => 'bg-amber-500', 'critica' => 'bg-marca-600'] as $k => $c)
                            <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-1 py-1.5 text-xs font-semibold transition"
                                   :class="p === '{{ $k }}' ? 'bg-white text-carbon-900 shadow-sm' : 'text-carbon-500'">
                                <input type="radio" name="prioridad" value="{{ $k }}" x-model="p" class="sr-only"><span class="size-2 rounded-full {{ $c }}"></span>{{ \App\Models\OrdenTrabajo::PRIORIDADES[$k] }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <x-campo nombre="especialidad_id" etiqueta="Especialidad">
                    <select id="especialidad_id" name="especialidad_id" x-model="especialidad" class="campo">
                        <option value="">Cualquiera</option>
                        @foreach ($especialidades as $e)<option value="{{ $e->id }}">{{ $e->nombre }}</option>@endforeach
                    </select>
                </x-campo>
            </div>
        </section>

        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Asignación y fechas</h3></div>
            <div class="space-y-4 p-5">
                @if ($puedeAsignar)
                    <x-campo nombre="responsable_id" etiqueta="Responsable" ayuda="Se le notifica en el sistema y por correo.">
                        <select id="responsable_id" name="responsable_id" x-model="responsable" class="campo">
                            <option value="">Sin asignar</option>
                            @foreach ($tecnicos as $t)
                                <option value="{{ $t->id }}" x-show="!especialidad || especialidad == '{{ $t->especialidad_id }}' || responsable == '{{ $t->id }}'">{{ $t->name }}{{ $t->especialidad ? ' · '.$t->especialidad->nombre : '' }}</option>
                            @endforeach
                        </select>
                    </x-campo>
                    <div x-data="{ abierto: {{ $ayudantes ? 'true' : 'false' }} }">
                        <button type="button" class="flex items-center gap-1 text-sm font-semibold text-carbon-700" @click="abierto = !abierto">
                            <x-icono n="usuarios" clase="size-4" /> Técnicos de apoyo <x-icono n="abajo" clase="size-4 transition" ::class="abierto && 'rotate-180'" />
                        </button>
                        <div x-show="abierto" x-collapse class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-lg p-1 ring-1 ring-carbon-200">
                            @foreach ($tecnicos as $t)
                                <label class="flex items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-carbon-50" x-show="responsable != '{{ $t->id }}'">
                                    <input type="checkbox" name="ayudantes[]" value="{{ $t->id }}" class="check" @checked(in_array($t->id, $ayudantes))> {{ $t->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="flex items-start gap-2 rounded-lg bg-carbon-50 px-3 py-2.5 text-sm text-carbon-600">
                        <x-icono n="campana" clase="mt-0.5 size-4 shrink-0" /> El coordinador de mantenimiento recibirá tu reporte y lo asignará a un técnico.
                    </p>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <x-campo nombre="fecha_inicio" etiqueta="Inicio" tipo="date" :valor="$ot->fecha_inicio?->format('Y-m-d')" />
                    <x-campo nombre="fecha_vencimiento" etiqueta="Vence" tipo="date" :valor="$ot->fecha_vencimiento?->format('Y-m-d')" />
                </div>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <a href="{{ $ot->exists ? route('ot.show', $ot) : route('ot.index') }}" class="btn-secundario flex-1">Cancelar</a>
            <button class="btn-primario flex-[2]"><x-icono n="check" clase="size-4" /> {{ $ot->exists ? 'Guardar cambios' : 'Crear orden' }}</button>
        </div>
    </div>
</form>
</x-layouts.app>
