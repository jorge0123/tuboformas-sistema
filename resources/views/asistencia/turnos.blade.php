<x-layouts.app titulo="Turnos">
<x-slot:migas><a href="{{ route('asistencia.index') }}" class="hover:text-carbon-800">Asistencia</a><x-icono n="derecha" clase="size-3" />Turnos</x-slot:migas>
@php $dias = \App\Models\Turno::DIAS; @endphp

<p class="mb-5 max-w-3xl text-sm text-carbon-600">Cada técnico tiene su turno (se asigna en <a href="{{ route('usuarios.index') }}" class="font-semibold text-marca-700 hover:underline">Usuarios</a>).
    Con turno, marca entrada y salida; se cuenta tarde si entra después de la tolerancia, y si no marca salida el sistema la cierra a la hora de fin del turno.
    Un turno que sale antes de la hora de entrada (ej. 22:00 a 06:00) termina al día siguiente.</p>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <section class="tarjeta h-fit">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Nuevo turno</h3></div>
        <form method="POST" action="{{ route('turnos.store') }}" class="space-y-4 p-5">@csrf
            <x-campo nombre="nombre" etiqueta="Nombre" requerido placeholder="Turno A (mañana)" />
            <div class="grid grid-cols-2 gap-3">
                <x-campo nombre="hora_entrada" etiqueta="Entrada" tipo="time" valor="07:00" requerido />
                <x-campo nombre="hora_salida" etiqueta="Salida" tipo="time" valor="16:00" requerido />
            </div>
            <div>
                <span class="etiqueta">Días</span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($dias as $n => $d)
                        <label class="cursor-pointer"><input type="checkbox" name="dias[]" value="{{ $n }}" class="peer sr-only" @checked(in_array($n, old('dias', [1, 2, 3, 4, 5])))>
                            <span class="block rounded-lg px-2.5 py-1.5 text-xs font-semibold ring-1 ring-carbon-200 transition peer-checked:bg-carbon-900 peer-checked:text-white peer-checked:ring-carbon-900">{{ $d }}</span></label>
                    @endforeach
                </div>
            </div>
            <x-campo nombre="tolerancia" etiqueta="Tolerancia (minutos)" tipo="number" valor="10" min="0" max="120" requerido ayuda="Después de esto se cuenta como llegada tarde." />
            <button class="btn-primario w-full"><x-icono n="mas" clase="size-4" /> Crear turno</button>
        </form>
    </section>

    <div class="space-y-4 xl:col-span-2">
        @forelse ($turnos as $t)
            <form method="POST" action="{{ route('turnos.update', $t) }}" class="tarjeta p-5 {{ $t->activo ? '' : 'opacity-60' }}" x-data="{ cambiado: false }" @input="cambiado = true" @change="cambiado = true">
                @csrf @method('PUT')
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-display text-lg font-bold">{{ $t->nombre }}</p>
                        <p class="text-sm text-carbon-500">{{ $t->horario() }} · {{ \App\Support\Formato::duracion($t->horas()) }} · {{ $t->textoDias() }}</p>
                    </div>
                    <span class="insignia-gris"><x-icono n="usuarios" clase="size-3" /> {{ $t->usuarios_count }} {{ $t->usuarios_count === 1 ? 'persona' : 'personas' }}</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-6">
                    <div class="col-span-2"><label class="etiqueta">Nombre</label><input name="nombre" value="{{ $t->nombre }}" required class="campo"></div>
                    <div><label class="etiqueta">Entrada</label><input type="time" name="hora_entrada" value="{{ substr($t->hora_entrada, 0, 5) }}" required class="campo"></div>
                    <div><label class="etiqueta">Salida</label><input type="time" name="hora_salida" value="{{ substr($t->hora_salida, 0, 5) }}" required class="campo"></div>
                    <div class="col-span-2"><label class="etiqueta">Tolerancia (min)</label><input type="number" name="tolerancia" value="{{ $t->tolerancia }}" min="0" max="120" required class="campo"></div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                    @foreach ($dias as $n => $d)
                        <label class="cursor-pointer"><input type="checkbox" name="dias[]" value="{{ $n }}" class="peer sr-only" @checked(in_array($n, array_map('intval', $t->dias)))>
                            <span class="block rounded-lg px-2.5 py-1.5 text-xs font-semibold ring-1 ring-carbon-200 transition peer-checked:bg-carbon-900 peer-checked:text-white peer-checked:ring-carbon-900">{{ $d }}</span></label>
                    @endforeach
                    <label class="ml-auto flex items-center gap-1.5 text-xs text-carbon-600"><input type="checkbox" name="activo" value="1" class="check" @checked($t->activo)> Activo</label>
                    <button class="btn-secundario btn-sm" x-show="cambiado" x-cloak x-transition><x-icono n="check" clase="size-4" /> Guardar</button>
                </div>
            </form>
        @empty
            <div class="tarjeta"><x-vacio icono="calendario" titulo="Sin turnos" texto="Crea el primer turno a la izquierda." /></div>
        @endforelse
    </div>
</div>
</x-layouts.app>
