<x-layouts.app titulo="Inicio">
@php
    $hora = (int) now()->format('H');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $u = auth()->user();
    $coordina = $u->can('ot.ver_todas');
@endphp

{{-- Saludo y acción principal --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-medium text-carbon-500">{{ ucfirst(now()->translatedFormat('l j \d\e F')) }}</p>
        <h2 class="font-display text-2xl font-extrabold tracking-tight text-carbon-900">{{ $saludo }}, {{ strtok($u->name, ' ') }}</h2>
        @isset($d['ot'])
            <p class="mt-1 text-sm text-carbon-600">
                @if ($d['ot']['atrasadas'])
                    Hay <a href="{{ route('ot.index', ['situacion' => 'atrasada']) }}" class="font-semibold text-marca-700 hover:underline">{{ $d['ot']['atrasadas'] }} {{ $d['ot']['atrasadas'] === 1 ? 'orden atrasada' : 'órdenes atrasadas' }}</a>{{ $d['ot']['por_vencer'] ? ' y '.$d['ot']['por_vencer'].' por vencer' : '' }}.
                @elseif ($d['ot']['por_vencer'])
                    {{ $d['ot']['por_vencer'] }} {{ $d['ot']['por_vencer'] === 1 ? 'orden vence' : 'órdenes vencen' }} en los próximos dos días.
                @else
                    Ninguna orden atrasada. Todo al día.
                @endif
            </p>
        @endisset
    </div>
    @can('ot.crear')
        <a href="{{ route('ot.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> {{ $coordina ? 'Nueva orden de trabajo' : 'Reportar falla' }}</a>
    @endcan
</div>

{{-- Mi jornada (quien marca asistencia) --}}
@isset($d['jornada'])
@php $j = $d['jornada']; @endphp
<section class="mb-6 overflow-hidden rounded-2xl {{ $j['asistencia'] ? 'bg-carbon-900 text-white' : 'tarjeta' }}">
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
        <span class="grid size-14 shrink-0 place-items-center rounded-2xl {{ $j['asistencia'] ? 'bg-white/10 text-emerald-300' : 'bg-marca-50 text-marca-600' }}"><x-icono n="reloj" clase="size-7" /></span>
        <div class="min-w-0 flex-1">
            @if ($j['asistencia'])
                <p class="text-sm text-carbon-300">Mi jornada · entraste a las {{ $j['asistencia']->entrada_at->format('H:i') }}</p>
                @if ($j['tramo'])
                    <p class="font-display text-xl font-extrabold">Trabajando en {{ $j['tramo']->orden->folio }}</p>
                    <p class="truncate text-sm text-carbon-300">{{ $j['tramo']->orden->titulo }} · desde las {{ $j['tramo']->inicio_at->format('H:i') }} ({{ \App\Support\Formato::duracion($j['tramo']->horas()) }})</p>
                @else
                    <p class="font-display text-xl font-extrabold">Sin orden en curso</p>
                    <p class="text-sm text-carbon-300">Abre una de tus órdenes y pulsa "Trabajar en esta orden" para que cuente tu tiempo.</p>
                @endif
            @else
                <p class="text-sm text-carbon-500">Mi jornada · {{ $u->turno->nombre }} ({{ $u->turno->horario() }})</p>
                <p class="font-display text-xl font-extrabold">Marca tu entrada para empezar</p>
                <p class="text-sm text-carbon-500">Tu tiempo en las órdenes solo cuenta mientras estás marcado.</p>
            @endif
        </div>
        @if ($j['asistencia'])
            <div class="grid grid-cols-2 gap-6 sm:text-right">
                <div><p class="text-xs text-carbon-400">En turno</p><p class="font-display text-2xl font-extrabold tabular-nums">{{ \App\Support\Formato::duracion($j['asistencia']->horas()) }}</p></div>
                <div><p class="text-xs text-carbon-400">En órdenes</p><p class="font-display text-2xl font-extrabold tabular-nums text-emerald-300">{{ \App\Support\Formato::duracion($j['en_ot_hoy']) }}</p></div>
            </div>
            @if ($j['tramo'])<a href="{{ route('ot.show', $j['tramo']->orden) }}" class="btn bg-white text-carbon-900 hover:bg-carbon-100">Ir a la orden</a>@endif
        @else
            <form method="POST" action="{{ route('asistencia.entrada') }}">@csrf<button class="btn-primario max-sm:w-full"><x-icono n="reloj" clase="size-4" /> Marcar entrada</button></form>
        @endif
    </div>
</section>
@endisset

{{-- Indicadores --}}
@isset($d['ot'])
<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
    <x-kpi titulo="Órdenes abiertas" :valor="$d['ot']['abiertas']" icono="portapapeles" :href="route('ot.index')" :nota="$d['ot']['en_espera'] ? $d['ot']['en_espera'].' esperando repuesto u otra cosa' : 'Pendientes y en progreso'" />
    <x-kpi titulo="Atrasadas" :valor="$d['ot']['atrasadas']" icono="alerta" :tono="$d['ot']['atrasadas'] ? 'rojo' : 'verde'" :href="route('ot.index', ['situacion' => 'atrasada'])" nota="Pasaron su fecha sin completarse" />
    <x-kpi titulo="Vencen en 2 días" :valor="$d['ot']['por_vencer']" icono="reloj" tono="ambar" :href="route('ot.index', ['situacion' => 'por_vencer'])" nota="Atenderlas antes de que se atrasen" />
    @isset($d['ocupacion_semana'])
        <x-kpi titulo="Ocupación del equipo" :valor="$d['ocupacion_semana'] !== null ? $d['ocupacion_semana'].'%' : '—'" icono="usuarios" tono="azul" :href="route('reportes.ocupacion')" nota="Tiempo en órdenes, últimos 7 días" />
    @else
        <x-kpi titulo="Completadas este mes" :valor="$d['ot']['completadas_mes']" icono="check-circulo" tono="verde" :href="route('ot.index', ['estado' => 'completada'])" />
    @endisset
</div>
@endisset

@if (! empty($d['sin_asignar']) || ! empty($d['ajustes_pendientes']))
<div class="mt-4 flex flex-wrap gap-3">
    @if (! empty($d['sin_asignar']))
        <a href="{{ route('ot.index', ['sin_responsable' => 1]) }}" class="flex flex-1 items-center gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 transition hover:bg-amber-100">
            <x-icono n="alerta" clase="size-5 shrink-0" /><span><b>{{ $d['sin_asignar'] }}</b> {{ $d['sin_asignar'] === 1 ? 'orden espera que se le asigne' : 'órdenes esperan que se les asigne' }} un técnico.</span><x-icono n="derecha" clase="ml-auto size-4" />
        </a>
    @endif
    @if (! empty($d['ajustes_pendientes']))
        <a href="{{ route('movimientos.index', ['estado' => 'pendiente']) }}" class="flex flex-1 items-center gap-3 rounded-xl bg-carbon-50 px-4 py-3 text-sm text-carbon-700 ring-1 ring-carbon-200 transition hover:bg-carbon-100">
            <x-icono n="cajas" clase="size-5 shrink-0" /><span><b>{{ $d['ajustes_pendientes'] }}</b> {{ $d['ajustes_pendientes'] === 1 ? 'ajuste de repuestos espera' : 'ajustes de repuestos esperan' }} tu aprobación.</span><x-icono n="derecha" clase="ml-auto size-4" />
        </a>
    @endif
</div>
@endif

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        {{-- Mis órdenes --}}
        @if (isset($d['mis_ot']) && ($d['mis_ot']->isNotEmpty() || ! $coordina))
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Mis órdenes</h3>
                <a href="{{ route('ot.index', ['mias' => 1]) }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver todas</a>
            </div>
            @forelse ($d['mis_ot'] as $ot)
                <a href="{{ route('ot.show', $ot) }}" class="flex items-center gap-4 border-b border-carbon-50 px-5 py-3.5 transition last:border-0 hover:bg-carbon-50/70">
                    <span class="h-10 w-1 shrink-0 rounded-full" style="background: {{ \App\Support\Colores::ESTADOS_OT[$ot->estado] }}" title="{{ \App\Models\OrdenTrabajo::ESTADOS[$ot->estado] }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-semibold text-carbon-400">{{ $ot->folio }}</span>
                            <x-ot.estado :estado="$ot->estado" />
                            <x-ot.situacion :ot="$ot" />
                        </div>
                        <p class="mt-0.5 truncate text-sm font-semibold text-carbon-900">{{ $ot->titulo }}</p>
                        <p class="truncate text-xs text-carbon-500">{{ $ot->maquina?->etiqueta() ?? 'Sin máquina' }}{{ $ot->fecha_vencimiento ? ' · vence '.$ot->fecha_vencimiento->format('d/m') : '' }}</p>
                    </div>
                    <div class="hidden w-28 sm:block">
                        <div class="mb-1 flex justify-between text-xs"><span class="text-carbon-500">Avance</span><span class="font-semibold tabular-nums">{{ $ot->progreso }}%</span></div>
                        <div class="barra"><span style="width: {{ $ot->progreso }}%"></span></div>
                    </div>
                </a>
            @empty
                <x-vacio icono="check-circulo" titulo="No tienes órdenes abiertas" texto="Cuando te asignen una tarea aparecerá aquí y te llegará una notificación." />
            @endforelse
        </section>
        @endif

        {{-- Requieren atención --}}
        @isset($d['urgentes'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <div><h3 class="tarjeta-titulo">Requieren atención</h3><p class="text-xs text-carbon-500">Críticas, atrasadas o que vencen en dos días</p></div>
                <a href="{{ route('ot.kanban') }}" class="text-sm font-semibold text-marca-700 hover:underline">Tablero</a>
            </div>
            @if ($d['urgentes']->isEmpty())
                <x-vacio icono="check-circulo" titulo="Todo en orden" texto="No hay órdenes críticas, atrasadas ni por vencer." />
            @else
            <div class="divide-y divide-carbon-50">
                @foreach ($d['urgentes'] as $ot)
                    <a href="{{ route('ot.show', $ot) }}" class="flex items-center gap-4 px-5 py-3 transition hover:bg-carbon-50/70">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-semibold text-carbon-400">{{ $ot->folio }}</span><x-ot.situacion :ot="$ot" /><x-ot.prioridad :prioridad="$ot->prioridad" /></div>
                            <p class="mt-0.5 truncate text-sm font-semibold text-carbon-900">{{ $ot->titulo }}</p>
                            <p class="truncate text-xs text-carbon-500">{{ $ot->maquina?->etiqueta() ?? 'General' }}</p>
                        </div>
                        <div class="hidden shrink-0 text-right sm:block">
                            <p class="text-sm font-medium {{ $ot->responsable ? 'text-carbon-700' : 'text-amber-700' }}">{{ $ot->responsable?->name ?? 'Sin asignar' }}</p>
                            <p class="text-xs text-carbon-500">{{ $ot->fecha_vencimiento ? 'Vence '.$ot->fecha_vencimiento->format('d/m') : 'Sin fecha' }} · {{ $ot->progreso }}%</p>
                        </div>
                    </a>
                @endforeach
            </div>
            @endif
        </section>
        @endisset

        {{-- ¿Vamos al día? --}}
        @isset($d['semanas'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <div><h3 class="tarjeta-titulo">¿Vamos al día?</h3><p class="text-xs text-carbon-500">Órdenes creadas y completadas por semana. Si las completadas quedan abajo, el trabajo pendiente crece.</p></div>
                @can('reportes.mantenimiento')<a href="{{ route('reportes.mantenimiento') }}" class="text-sm font-semibold text-marca-700 hover:underline">Reporte</a>@endcan
            </div>
            <div class="p-5">
                <x-grafica.columnas :datos="$d['semanas']" alto="h-36" :series="['creadas' => ['Creadas', '#d6d1cb'], 'completadas' => ['Completadas', '#2a78d6']]" />
            </div>
        </section>
        @endisset
    </div>

    {{-- Columna derecha --}}
    <div class="space-y-6">
        {{-- Equipo de hoy --}}
        @isset($d['equipo'])
        @php $enTurno = $d['equipo']->where('estado', 'en_turno'); $faltan = $d['equipo']->where('estado', 'falta'); @endphp
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <div><h3 class="tarjeta-titulo">Equipo de hoy</h3><p class="text-xs text-carbon-500">{{ $enTurno->count() }} en turno{{ $faltan->count() ? ' · '.$faltan->count().' sin llegar' : '' }}</p></div>
                <a href="{{ route('asistencia.index') }}" class="text-sm font-semibold text-marca-700 hover:underline">Asistencia</a>
            </div>
            @forelse ($d['equipo']->whereIn('estado', ['en_turno', 'falta', 'salio']) as $f)
                <div class="flex items-center gap-3 border-b border-carbon-50 px-5 py-3 last:border-0">
                    <span class="relative grid size-9 shrink-0 place-items-center rounded-full bg-carbon-100 text-[11px] font-bold text-carbon-700">{{ $f->usuario->iniciales() }}
                        <span class="absolute -right-0.5 -bottom-0.5 size-3 rounded-full ring-2 ring-white {{ ['en_turno' => 'bg-emerald-500', 'falta' => 'bg-marca-600', 'salio' => 'bg-carbon-300'][$f->estado] }}"></span></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $f->usuario->name }}</p>
                        <p class="truncate text-xs text-carbon-500">
                            @if ($f->estado === 'falta') No ha marcado (entra {{ substr($f->usuario->turno->hora_entrada, 0, 5) }})
                            @elseif ($f->estado === 'salio') Ya salió · {{ \App\Support\Formato::duracion($f->horas) }}
                            @elseif ($f->tramo) <a href="{{ route('ot.show', $f->tramo->orden) }}" class="font-medium text-sky-700 hover:underline">{{ $f->tramo->orden->folio }}</a> · {{ $f->tramo->orden->titulo }}
                            @else Sin orden en curso @endif
                        </p>
                    </div>
                    @if ($f->estado !== 'falta')<span class="text-sm font-bold tabular-nums" title="Ocupación de hoy">{{ $f->ocupacion !== null ? $f->ocupacion.'%' : '' }}</span>@endif
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-carbon-500">Nadie tiene turno hoy todavía.</p>
            @endforelse
        </section>
        @endisset

        {{-- Órdenes abiertas por estado --}}
        @isset($d['por_estado'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Órdenes abiertas</h3><span class="text-sm font-bold tabular-nums">{{ $d['por_estado']->sum() }}</span></div>
            <div class="p-5">
                <x-grafica.apilada :datos="collect(['pendiente', 'en_progreso', 'en_espera'])->map(fn ($e) => ['etiqueta' => \App\Models\OrdenTrabajo::ESTADOS[$e], 'valor' => $d['por_estado'][$e] ?? 0, 'color' => \App\Support\Colores::ESTADOS_OT[$e], 'href' => route('ot.index', ['estado' => $e])])->all()" />
            </div>
        </section>
        @endisset

        {{-- Preventivos --}}
        @isset($d['preventivos'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <div><h3 class="tarjeta-titulo">Preventivos próximos</h3><p class="text-xs text-carbon-500">Próximas dos semanas</p></div>
                <a href="{{ route('calendario') }}" class="text-sm font-semibold text-marca-700 hover:underline">Calendario</a>
            </div>
            @forelse ($d['preventivos'] as $p)
                <div class="flex items-center gap-3 border-b border-carbon-50 px-5 py-3 last:border-0">
                    <div class="grid w-11 shrink-0 place-items-center rounded-lg bg-carbon-50 py-1 ring-1 ring-carbon-200">
                        <span class="text-[10px] font-bold text-marca-600 uppercase">{{ $p->proxima_fecha->translatedFormat('M') }}</span>
                        <span class="font-display text-lg leading-none font-extrabold">{{ $p->proxima_fecha->format('d') }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $p->titulo }}</p>
                        <p class="truncate text-xs text-carbon-500">{{ $p->maquina?->etiqueta() }} · {{ $p->responsable?->name ?? 'Sin responsable' }}</p>
                    </div>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-carbon-500">Sin preventivos en las próximas dos semanas.</p>
            @endforelse
        </section>
        @endisset

        {{-- Máquinas --}}
        @isset($d['maquinas'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Máquinas</h3><a href="{{ route('maquinas.index') }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver</a></div>
            <div class="grid grid-cols-3 divide-x divide-carbon-100 text-center">
                @foreach (['operativa' => ['Operativas', 'text-emerald-600'], 'en_mantenimiento' => ['En mantenimiento', 'text-sky-700'], 'fuera_servicio' => ['Fuera de servicio', 'text-marca-700']] as $e => [$t, $c])
                    <a href="{{ route('maquinas.index', ['estado' => $e]) }}" class="px-2 py-4 transition hover:bg-carbon-50"><p class="font-display text-2xl font-extrabold tabular-nums {{ $c }}">{{ $d['maquinas'][$e] ?? 0 }}</p><p class="text-xs text-carbon-500">{{ $t }}</p></a>
                @endforeach
            </div>
        </section>
        @endisset

        {{-- Repuestos: solo lo urgente --}}
        @if (! empty($d['bajo_minimo_total']))
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <div><h3 class="tarjeta-titulo">Repuestos por pedir</h3><p class="text-xs text-carbon-500">{{ $d['bajo_minimo_total'] }} bajo el mínimo</p></div>
                <a href="{{ route('productos.index', ['bajo_minimo' => 1]) }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver</a>
            </div>
            @foreach ($d['bajo_minimo'] as $p)
                <a href="{{ route('productos.show', $p) }}" class="flex items-center justify-between gap-3 border-b border-carbon-50 px-5 py-2.5 text-sm transition last:border-0 hover:bg-carbon-50/70">
                    <span class="truncate">{{ $p->nombre }}</span>
                    <span class="shrink-0 text-xs tabular-nums text-carbon-500"><b class="text-marca-700">@num($p->existencia)</b> / @num($p->stock_minimo) {{ $p->unidad->abreviatura }}</span>
                </a>
            @endforeach
        </section>
        @endif
    </div>
</div>
</x-layouts.app>
