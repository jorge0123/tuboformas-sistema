<x-layouts.app titulo="Calendario">
@php
    $colores = [
        'pendiente' => 'bg-carbon-100 text-carbon-800 ring-carbon-200',
        'en_progreso' => 'bg-sky-50 text-sky-900 ring-sky-200',
        'en_espera' => 'bg-amber-50 text-amber-900 ring-amber-200',
        'completada' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        'programado' => 'bg-white text-violet-900 ring-violet-200 border-dashed',
    ];
    $puedeCrear = auth()->user()->can('ot.crear');
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="inline-flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-carbon-200">
        @canany(['ot.ver_todas', 'ot.ver_propias'])
        <a href="{{ route('ot.index') }}" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold text-carbon-600 hover:text-carbon-900"><x-icono n="lista" clase="size-4" /> Lista</a>
        <a href="{{ route('ot.kanban') }}" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold text-carbon-600 hover:text-carbon-900"><x-icono n="kanban" clase="size-4" /> Kanban</a>
        @endcanany
        <span class="flex items-center gap-1.5 rounded-md bg-carbon-900 px-3 py-1.5 text-sm font-semibold text-white"><x-icono n="calendario" clase="size-4" /> Calendario</span>
    </div>
    <form method="GET" class="flex flex-wrap items-center gap-2" x-data @change="$el.requestSubmit()">
        <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
        <select name="especialidad" class="campo w-auto">
            <option value="">Toda especialidad</option>
            @foreach ($especialidades as $e)<option value="{{ $e->id }}" @selected(request('especialidad') == $e->id)>{{ $e->nombre }}</option>@endforeach
        </select>
        @can('ot.ver_todas')
        <select name="responsable" class="campo w-auto">
            <option value="">Todos los técnicos</option>
            @foreach ($tecnicos as $t)<option value="{{ $t->id }}" @selected(request('responsable') == $t->id)>{{ $t->name }}</option>@endforeach
        </select>
        @endcan
    </form>
</div>

<div class="tarjeta overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-carbon-100 px-5 py-4">
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['mes' => $mes->copy()->subMonth()->format('Y-m')]) }}" class="btn-secundario btn-icono" aria-label="Mes anterior"><x-icono n="izquierda" clase="size-4" /></a>
            <a href="{{ request()->fullUrlWithQuery(['mes' => $mes->copy()->addMonth()->format('Y-m')]) }}" class="btn-secundario btn-icono" aria-label="Mes siguiente"><x-icono n="derecha" clase="size-4" /></a>
            <h2 class="ml-2 font-display text-xl font-extrabold capitalize">{{ $mes->translatedFormat('F Y') }}</h2>
            @unless ($mes->isSameMonth(now()))
                <a href="{{ request()->fullUrlWithQuery(['mes' => now()->format('Y-m')]) }}" class="btn-fantasma btn-sm">Hoy</a>
            @endunless
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs text-carbon-600">
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-carbon-300"></span>Pendiente</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-sky-400"></span>En progreso</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-amber-400"></span>En espera</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-emerald-400"></span>Completada</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm border border-dashed border-violet-400"></span>Preventivo programado</span>
        </div>
    </div>

    <div class="grid grid-cols-7 border-b border-carbon-100 bg-carbon-50/70 text-center text-xs font-semibold tracking-wide text-carbon-500 uppercase">
        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d)<div class="py-2">{{ $d }}</div>@endforeach
    </div>
    <div class="grid grid-cols-7">
        @for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay())
            @php
                $clave = $dia->toDateString();
                $items = $porDia[$clave] ?? collect();
                $fuera = ! $dia->isSameMonth($mes);
                $hoy = $dia->isToday();
            @endphp
            <div class="group relative min-h-32 border-r border-b border-carbon-100 p-1.5 [&:nth-child(7n)]:border-r-0 {{ $fuera ? 'bg-carbon-50/60' : 'bg-white' }}">
                <div class="mb-1 flex items-center justify-between px-1">
                    <span class="grid size-7 place-items-center rounded-full text-sm font-semibold {{ $hoy ? 'bg-marca-600 text-white' : ($fuera ? 'text-carbon-300' : 'text-carbon-700') }}">{{ $dia->day }}</span>
                    @if ($puedeCrear)
                        <a href="{{ route('ot.create', ['fecha' => $clave]) }}" class="grid size-6 place-items-center rounded-md text-carbon-400 opacity-0 transition group-hover:opacity-100 hover:bg-carbon-100 hover:text-marca-700" title="Nueva tarea para este día">
                            <x-icono n="mas" clase="size-4" />
                        </a>
                    @endif
                </div>
                <div class="space-y-1" x-data="{ todos: false }">
                    @foreach ($items as $i => $e)
                        <a @if ($e['url']) href="{{ $e['url'] }}" @endif title="{{ $e['titulo'] }} — {{ $e['detalle'] }}"
                           @if ($i >= 3) x-show="todos" x-cloak @endif
                           class="flex items-center gap-1 truncate rounded-md border border-transparent px-1.5 py-1 text-[11px] leading-tight font-semibold ring-1 transition hover:brightness-95 {{ $colores[$e['estado']] ?? $colores['pendiente'] }}">
                            @if ($e['tipo'] === 'plan')<x-icono n="repetir" clase="size-3 shrink-0" />@elseif ($e['situacion'] === 'atrasada')<x-icono n="alerta" clase="size-3 shrink-0 text-marca-600" />@endif
                            <span class="truncate">{{ $e['titulo'] }}</span>
                        </a>
                    @endforeach
                    @if ($items->count() > 3)
                        <button class="w-full rounded px-1.5 text-left text-[11px] font-semibold text-carbon-500 hover:text-carbon-900" @click="todos = !todos" x-text="todos ? 'Ver menos' : '+{{ $items->count() - 3 }} más'"></button>
                    @endif
                </div>
            </div>
        @endfor
    </div>
</div>
<p class="mt-3 text-xs text-carbon-500">Los preventivos programados se convierten en órdenes de trabajo automáticamente unos días antes de su fecha (según la anticipación del plan).</p>
</x-layouts.app>
