<x-layouts.app :titulo="$todos ? 'Asistencia' : 'Mi asistencia'">
@php $u = auth()->user(); @endphp

{{-- Mi turno --}}
@if ($u->marcaAsistencia())
<section class="tarjeta mb-6 overflow-hidden">
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
        <span class="grid size-14 shrink-0 place-items-center rounded-2xl {{ $abierta ? 'bg-emerald-50 text-emerald-600' : 'bg-carbon-100 text-carbon-500' }}"><x-icono n="reloj" clase="size-7" /></span>
        <div class="min-w-0 flex-1">
            @if ($abierta)
                <p class="font-display text-xl font-extrabold">En turno desde las {{ $abierta->entrada_at->format('H:i') }}</p>
                <p class="text-sm text-carbon-500">Llevas {{ \App\Support\Formato::duracion($abierta->horas()) }}
                    @if ($tramo) · trabajando en <a href="{{ route('ot.show', $tramo->orden) }}" class="font-semibold text-marca-700 hover:underline">{{ $tramo->orden->folio }}</a> desde las {{ $tramo->inicio_at->format('H:i') }}@else · sin orden en curso @endif
                </p>
            @else
                <p class="font-display text-xl font-extrabold">Fuera de turno</p>
                <p class="text-sm text-carbon-500">{{ $u->turno->nombre }}: {{ $u->turno->horario() }}, {{ mb_strtolower($u->turno->textoDias()) }}. Marca tu entrada para registrar trabajo en tus órdenes.</p>
            @endif
        </div>
        @if ($abierta)
            <form method="POST" action="{{ route('asistencia.salida') }}" data-confirmar="Se detiene el tiempo de la orden en la que estás trabajando; sigue al marcar tu próxima entrada." data-titulo="Marcar salida" data-boton="Marcar salida">@csrf
                <button class="btn-oscuro max-sm:w-full"><x-icono n="salir" clase="size-4" /> Marcar salida</button>
            </form>
        @else
            <form method="POST" action="{{ route('asistencia.entrada') }}">@csrf
                <button class="btn-primario max-sm:w-full"><x-icono n="reloj" clase="size-4" /> Marcar entrada</button>
            </form>
        @endif
    </div>
</section>
@endif

{{-- Personal de hoy --}}
@if ($todos)
@php
    $cuenta = $hoy->countBy('estado');
    $estilos = [
        'en_turno' => ['En turno', 'insignia-verde'], 'falta' => ['No ha llegado', 'insignia-roja'],
        'por_entrar' => ['Entra más tarde', 'insignia-gris'], 'salio' => ['Ya salió', 'insignia-azul'], 'libre' => ['Descansa hoy', 'insignia-gris'],
    ];
@endphp
<div class="mb-4 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-kpi titulo="En turno ahora" :valor="$cuenta['en_turno'] ?? 0" icono="usuarios" tono="verde" />
    <x-kpi titulo="Trabajando en una orden" :valor="$hoy->whereNotNull('tramo')->count()" icono="llave" tono="azul" :nota="($cuenta['en_turno'] ?? 0) ? 'de '.($cuenta['en_turno'] ?? 0).' en turno' : null" />
    <x-kpi titulo="No han llegado" :valor="$cuenta['falta'] ?? 0" icono="alerta" tono="rojo" nota="Su turno ya empezó" />
    <x-kpi titulo="Llegaron tarde hoy" :valor="$hoy->where('tarde', '>', 0)->count()" icono="reloj" tono="ambar" />
</div>

<section class="tarjeta mb-6 overflow-hidden">
    <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Personal de hoy</h3><span class="text-xs text-carbon-500">{{ ucfirst(now()->translatedFormat('l j \d\e F')) }}</span></div>
    @if ($hoy->isEmpty())
        <x-vacio icono="usuarios" titulo="Nadie tiene turno asignado" texto="Asigna un turno a cada técnico en Usuarios para que marque asistencia." />
    @else
    <div class="divide-y divide-carbon-50">
        @foreach ($hoy as $f)
            <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-carbon-900 text-xs font-bold text-white">{{ $f->usuario->iniciales() }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $f->usuario->name }}</p>
                    <p class="truncate text-xs text-carbon-500">{{ $f->usuario->turno->nombre }} · {{ $f->usuario->turno->horario() }}{{ $f->usuario->especialidad ? ' · '.$f->usuario->especialidad->nombre : '' }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                    @if ($f->tramo)
                        <a href="{{ route('ot.show', $f->tramo->orden) }}" class="insignia-azul hover:underline"><x-icono n="llave" clase="size-3" /> {{ $f->tramo->orden->folio }} · {{ \App\Support\Formato::duracion($f->tramo->horas()) }}</a>
                    @endif
                    @if ($f->tarde)<span class="insignia-ambar">{{ $f->tarde }} min tarde</span>@endif
                    @if ($f->asistencia)<span class="tabular-nums text-carbon-500">{{ $f->asistencia->entrada_at->format('H:i') }}{{ $f->asistencia->salida_at ? ' – '.$f->asistencia->salida_at->format('H:i') : '' }}</span>@endif
                    <span class="{{ $estilos[$f->estado][1] }}">{{ $estilos[$f->estado][0] }}</span>
                </div>
            </div>
        @endforeach
    </div>
    @endif
</section>
@endif

{{-- Registros --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('asistencia.index')" :buscar="false">
        @if ($todos)
        <select name="persona" class="campo w-auto">
            <option value="">Todo el personal</option>
            @foreach ($personal as $p)<option value="{{ $p->id }}" @selected(request('persona') == $p->id)>{{ $p->name }}</option>@endforeach
        </select>
        @endif
        <select name="ver" class="campo w-auto">
            <option value="">Todas las marcas</option>
            <option value="tarde" @selected(request('ver') === 'tarde')>Llegadas tarde</option>
            <option value="sin_salida" @selected(request('ver') === 'sin_salida')>Salida sin marcar</option>
        </select>
        <input type="date" name="desde" value="{{ request('desde') }}" class="campo w-auto" onchange="this.form.requestSubmit()" title="Desde">
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="campo w-auto" onchange="this.form.requestSubmit()" title="Hasta">
    </x-filtros>
    <div class="flex flex-wrap gap-2">
        @can('asistencia.ver')<a href="{{ route('reportes.ocupacion') }}" class="btn-secundario"><x-icono n="grafica" clase="size-4" /> Ocupación</a>@endcan
        @can('reportes.exportar')<a href="{{ route('asistencia.exportar', request()->query()) }}" class="btn-secundario"><x-icono n="descargar" clase="size-4" /> Excel</a>@endcan
    </div>
</div>

<div class="tarjeta overflow-hidden">
    @if ($registros->isEmpty())
        <x-vacio icono="reloj" titulo="Sin marcas" texto="No hay registros de asistencia con esos filtros." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr>@if ($todos)<th>Persona</th>@endif<th>Fecha</th><th>Entrada</th><th>Salida</th><th class="text-right">Horas</th><th>Observaciones</th>@can('asistencia.gestionar')<th></th>@endcan</tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($registros as $a)
                @php $tarde = $a->minutosTarde(); $extra = $a->horasExtra(); @endphp
                <tr>
                    @if ($todos)<td class="font-semibold text-carbon-900">{{ $a->user->name }}</td>@endif
                    <td class="whitespace-nowrap tabular-nums">{{ ucfirst($a->fecha->translatedFormat('D d/m/Y')) }}</td>
                    <td class="tabular-nums">{{ $a->entrada_at->format('H:i') }}</td>
                    <td class="tabular-nums">{{ $a->salida_at ? $a->salida_at->format($a->salida_at->isSameDay($a->entrada_at) ? 'H:i' : 'd/m H:i') : '—' }}</td>
                    <td class="tabla-num font-semibold">{{ \App\Support\Formato::duracion($a->horas()) }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @unless ($a->salida_at)<span class="insignia-verde">En turno</span>@endunless
                            @if ($tarde)<span class="insignia-ambar">{{ $tarde }} min tarde</span>@endif
                            @if ($extra >= 0.25)<span class="insignia-azul">+{{ \App\Support\Formato::duracion($extra) }} extra</span>@endif
                            @if ($a->salida_automatica)<span class="insignia-roja">Salida sin marcar</span>@endif
                            @if ($a->corregida_por)<span class="insignia-gris" title="{{ $a->notas }}">Corregida por {{ $a->corrector?->name }}</span>@endif
                        </div>
                    </td>
                    @can('asistencia.gestionar')
                    <td class="text-right"><button class="btn-fantasma btn-icono text-carbon-400 hover:text-marca-700" @click="$dispatch('abrir-modal', 'corregir-{{ $a->id }}')" aria-label="Corregir"><x-icono n="lapiz" clase="size-4" /></button></td>
                    @endcan
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $registros->links() }}
    @endif
</div>

@can('asistencia.gestionar')
@foreach ($registros as $a)
<x-modal :nombre="'corregir-'.$a->id" :titulo="'Corregir asistencia de '.$a->user->name">
    <form method="POST" action="{{ route('asistencia.update', $a) }}" class="space-y-4">@csrf @method('PUT')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><label class="etiqueta">Entrada</label><input type="datetime-local" name="entrada_at" value="{{ $a->entrada_at->format('Y-m-d\TH:i') }}" required class="campo"></div>
            <div><label class="etiqueta">Salida</label><input type="datetime-local" name="salida_at" value="{{ $a->salida_at?->format('Y-m-d\TH:i') }}" class="campo"></div>
        </div>
        <div><label class="etiqueta">Motivo</label><input name="notas" value="{{ $a->notas }}" required class="campo" placeholder="ej. Olvidó marcar; salió a las 17:30 según el supervisor"></div>
        <p class="text-xs text-carbon-500">Si cambias la salida, el tiempo de la orden que se detuvo con esa salida se ajusta igual.</p>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secundario" @click="$dispatch('cerrar-modal')">Cancelar</button><button class="btn-primario">Guardar</button></div>
    </form>
</x-modal>
@endforeach
@endcan
</x-layouts.app>
