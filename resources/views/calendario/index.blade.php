<x-layouts.app titulo="Calendario">
@php
    $colores = [
        'pendiente' => 'bg-carbon-100 text-carbon-800 ring-carbon-200',
        'en_progreso' => 'bg-sky-50 text-sky-900 ring-sky-200',
        'en_espera' => 'bg-amber-50 text-amber-900 ring-amber-200',
        'completada' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        'programado' => 'bg-white text-violet-900 ring-violet-200 border-dashed',
    ];
    $puntos = [
        'pendiente' => 'bg-carbon-300', 'en_progreso' => 'bg-sky-400', 'en_espera' => 'bg-amber-400',
        'completada' => 'bg-emerald-400', 'programado' => 'bg-violet-400',
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
    <form method="GET" class="flex flex-wrap items-center gap-2" data-filtro-vivo>
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
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-carbon-100 px-4 py-3 sm:px-5 sm:py-4">
        <div class="flex items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['mes' => $mes->copy()->subMonth()->format('Y-m')]) }}" class="btn-secundario btn-icono" aria-label="Mes anterior"><x-icono n="izquierda" clase="size-4" /></a>
            <a href="{{ request()->fullUrlWithQuery(['mes' => $mes->copy()->addMonth()->format('Y-m')]) }}" class="btn-secundario btn-icono" aria-label="Mes siguiente"><x-icono n="derecha" clase="size-4" /></a>
            <h2 class="ml-2 font-display text-lg font-extrabold capitalize sm:text-xl">{{ $mes->translatedFormat('F Y') }}</h2>
            @unless ($mes->isSameMonth(now()))
                <a href="{{ request()->fullUrlWithQuery(['mes' => now()->format('Y-m')]) }}" class="btn-fantasma btn-sm">Hoy</a>
            @endunless
        </div>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-carbon-600 sm:text-xs">
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
            <div class="group relative min-h-14 border-r border-b border-carbon-100 p-1 md:min-h-32 md:p-1.5 [&:nth-child(7n)]:border-r-0 {{ $fuera ? 'bg-carbon-50/60' : 'bg-white' }}">
                {{-- Celular: el día es un botón que baja a su lista en la agenda; los eventos se ven como puntos. --}}
                @if ($items->isNotEmpty() && ! $fuera)
                    <a href="#dia-{{ $clave }}" class="absolute inset-0 z-10 md:hidden" aria-label="{{ $dia->translatedFormat('j \d\e F') }}: {{ $items->count() }} {{ $items->count() === 1 ? 'tarea' : 'tareas' }}"></a>
                @endif
                <div class="mb-1 flex items-center justify-center px-1 md:justify-between">
                    <span class="grid size-7 place-items-center rounded-full text-sm font-semibold {{ $hoy ? 'bg-marca-600 text-white' : ($fuera ? 'text-carbon-300' : 'text-carbon-700') }}">{{ $dia->day }}</span>
                    @if ($puedeCrear)
                        <a href="{{ route('ot.create', ['fecha' => $clave]) }}" class="grid size-6 place-items-center rounded-md text-carbon-400 opacity-0 transition group-hover:opacity-100 hover:bg-carbon-100 hover:text-marca-700" title="Nueva tarea para este día">
                            <x-icono n="mas" clase="size-4" />
                        </a>
                    @endif
                </div>
                <div class="flex flex-wrap justify-center gap-0.5 md:hidden">
                    @foreach ($items->take(4) as $e)
                        <span class="size-1.5 rounded-full {{ $puntos[$e['estado']] ?? $puntos['pendiente'] }}"></span>
                    @endforeach
                </div>
                <div class="hidden space-y-1 md:block" x-data="{ todos: false }">
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
{{-- Agenda del mes (celular): lo mismo del calendario, legible y en orden. --}}
@php $diasMes = collect($porDia)->filter(fn ($v, $k) => \Illuminate\Support\Carbon::parse($k)->isSameMonth($mes) && count($v))->sortKeys(); @endphp
@php $pasados = $mes->isSameMonth(now()) ? $diasMes->keys()->filter(fn ($k) => $k < today()->toDateString())->count() : 0; @endphp
<div class="mt-4 space-y-3 md:hidden" x-data="{ antes: false }"
     @hashchange.window="if (location.hash.slice(5) < '{{ today()->toDateString() }}') { antes = true; setTimeout(() => document.querySelector(location.hash)?.scrollIntoView({ behavior: 'smooth' }), 250) }">
    {{-- En el mes actual la agenda empieza hoy; lo pasado queda plegado. --}}
    @if ($pasados)
        <button type="button" class="btn-fantasma btn-sm w-full" @click="antes = !antes" x-text="antes ? 'Ocultar días anteriores' : 'Ver {{ $pasados }} {{ $pasados === 1 ? 'día anterior' : 'días anteriores' }}'"></button>
    @endif
    @forelse ($diasMes as $clave => $items)
        @php $d = \Illuminate\Support\Carbon::parse($clave); $esPasado = $pasados && $clave < today()->toDateString(); @endphp
        <section id="dia-{{ $clave }}" @if ($esPasado) x-show="antes" x-collapse x-init="location.hash === '#dia-{{ $clave }}' && (antes = true)" @endif class="tarjeta scroll-mt-20 overflow-hidden target:ring-2 target:ring-marca-500">
            <h3 class="flex items-center gap-2 border-b border-carbon-100 px-4 py-2.5 text-sm font-bold capitalize {{ $d->isToday() ? 'bg-marca-50 text-marca-800' : ($d->isPast() ? 'text-carbon-500' : 'text-carbon-900') }}">
                {{ $d->translatedFormat('l j') }}
                @if ($d->isToday())<span class="insignia-roja">Hoy</span>@elseif ($d->isTomorrow())<span class="insignia-gris">Mañana</span>@endif
                <span class="ml-auto text-xs font-medium text-carbon-400">{{ count($items) }}</span>
            </h3>
            <ul class="divide-y divide-carbon-50">
                @foreach ($items as $e)
                    <li>
                        <a @if ($e['url']) href="{{ $e['url'] }}" @endif class="flex items-start gap-3 px-4 py-3 active:bg-carbon-50">
                            <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $puntos[$e['estado']] ?? $puntos['pendiente'] }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5 text-sm font-semibold text-carbon-900">
                                    @if ($e['tipo'] === 'plan')<x-icono n="repetir" clase="size-3.5 shrink-0 text-violet-500" />@elseif ($e['situacion'] === 'atrasada')<x-icono n="alerta" clase="size-3.5 shrink-0 text-marca-600" />@endif
                                    <span class="line-clamp-2">{{ $e['titulo'] }}</span>
                                </span>
                                <span class="block truncate text-xs text-carbon-500">{{ $e['detalle'] }}</span>
                            </span>
                            @if ($e['url'])<x-icono n="derecha" clase="mt-1 size-4 shrink-0 text-carbon-300" />@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <x-vacio icono="calendario" titulo="Nada programado este mes" texto="Cambia de mes o de filtros." />
    @endforelse
</div>

<p class="mt-3 text-xs text-carbon-500">Los preventivos programados se convierten en órdenes de trabajo automáticamente unos días antes de su fecha (según la anticipación del plan).</p>
</x-layouts.app>
