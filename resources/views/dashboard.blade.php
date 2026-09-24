<x-layouts.app titulo="Inicio">
@php
    $hora = (int) now()->format('H');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $u = auth()->user();
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-medium text-carbon-500">{{ ucfirst(now()->translatedFormat('l j \d\e F')) }}</p>
        <h2 class="font-display text-2xl font-extrabold tracking-tight text-carbon-900">{{ $saludo }}, {{ strtok($u->name, ' ') }}</h2>
    </div>
    <div class="flex flex-wrap gap-2">
        @can('ot.crear')
            <a href="{{ route('ot.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nueva orden de trabajo</a>
        @endcan
        @can('movimientos.crear')
            <a href="{{ route('movimientos.create') }}" class="btn-secundario"><x-icono n="flechas" clase="size-4" /> Registrar movimiento</a>
        @endcan
    </div>
</div>

{{-- Indicadores --}}
@isset($d['ot'])
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-kpi titulo="Órdenes abiertas" :valor="$d['ot']['abiertas']" icono="portapapeles" :href="route('ot.index')" />
    <x-kpi titulo="Atrasadas" :valor="$d['ot']['atrasadas']" icono="alerta" tono="rojo" :href="route('ot.index', ['situacion' => 'atrasada'])" nota="Vencieron sin completarse" />
    <x-kpi titulo="Por vencer (2 días)" :valor="$d['ot']['por_vencer']" icono="reloj" tono="ambar" :href="route('ot.index', ['situacion' => 'por_vencer'])" />
    <x-kpi titulo="Completadas este mes" :valor="$d['ot']['completadas_mes']" icono="check-circulo" tono="verde" :href="route('ot.index', ['estado' => 'completada'])" />
</div>
@endisset

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        {{-- Mis órdenes (técnicos y quien tenga OT asignadas) --}}
        @if (isset($d['mis_ot']) && ($d['mis_ot']->isNotEmpty() || ! $u->can('ot.ver_todas')))
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Mis órdenes de trabajo</h3>
                <a href="{{ route('ot.index', ['mias' => 1]) }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver todas</a>
            </div>
            @forelse ($d['mis_ot'] as $ot)
                <a href="{{ route('ot.show', $ot) }}" class="flex items-center gap-4 border-b border-carbon-50 px-5 py-3.5 transition last:border-0 hover:bg-carbon-50/70">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-semibold text-carbon-400">{{ $ot->folio }}</span>
                            <x-ot.situacion :ot="$ot" />
                        </div>
                        <p class="mt-0.5 truncate text-sm font-semibold text-carbon-900">{{ $ot->titulo }}</p>
                        <p class="truncate text-xs text-carbon-500">{{ $ot->maquina?->etiqueta() ?? 'Sin máquina' }} · vence @fecha($ot->fecha_vencimiento)</p>
                    </div>
                    <div class="hidden w-32 sm:block">
                        <div class="mb-1 flex justify-between text-xs"><span class="text-carbon-500">Avance</span><span class="font-semibold tabular-nums">{{ $ot->progreso }}%</span></div>
                        <div class="barra"><span style="width: {{ $ot->progreso }}%"></span></div>
                    </div>
                    <x-icono n="derecha" clase="size-4 text-carbon-300" />
                </a>
            @empty
                <x-vacio icono="check-circulo" titulo="No tienes órdenes abiertas" texto="Cuando te asignen una tarea aparecerá aquí y te llegará una notificación." />
            @endforelse
        </section>
        @endif

        {{-- Urgentes --}}
        @isset($d['urgentes'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Requieren atención</h3>
                <span class="text-xs text-carbon-500">Vencidas, por vencer o críticas</span>
            </div>
            @if ($d['urgentes']->isEmpty())
                <x-vacio icono="check-circulo" titulo="Todo en orden" texto="No hay órdenes vencidas ni críticas." />
            @else
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Orden</th><th>Responsable</th><th>Vence</th><th>Situación</th><th class="text-right">Avance</th></tr></thead>
                    <tbody class="divide-y divide-carbon-50">
                    @foreach ($d['urgentes'] as $ot)
                        <tr class="cursor-pointer" onclick="location='{{ route('ot.show', $ot) }}'">
                            <td class="max-w-xs">
                                <p class="truncate font-semibold text-carbon-900">{{ $ot->titulo }}</p>
                                <p class="truncate text-xs text-carbon-500">{{ $ot->folio }} · {{ $ot->maquina?->etiqueta() ?? 'General' }}</p>
                            </td>
                            <td class="whitespace-nowrap">{{ $ot->responsable?->name ?? 'Sin asignar' }}</td>
                            <td class="whitespace-nowrap tabular-nums">@fecha($ot->fecha_vencimiento)</td>
                            <td><x-ot.situacion :ot="$ot" /></td>
                            <td class="tabla-num font-semibold">{{ $ot->progreso }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>
        @endisset

        {{-- Estado de las órdenes y carga por técnico --}}
        @isset($d['por_estado'])
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <section class="tarjeta">
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Órdenes por estado</h3></div>
                <div class="tarjeta-cuerpo space-y-3">
                    @php $max = max(1, $d['por_estado']->max()); $colores = ['pendiente' => 'bg-carbon-400', 'en_progreso' => 'bg-sky-500', 'en_espera' => 'bg-amber-500', 'completada' => 'bg-emerald-500', 'cancelada' => 'bg-carbon-300']; @endphp
                    @foreach (\App\Models\OrdenTrabajo::ESTADOS as $clave => $nombre)
                        <div>
                            <div class="mb-1 flex justify-between text-sm"><span class="text-carbon-600">{{ $nombre }}</span><span class="font-semibold tabular-nums">{{ $d['por_estado'][$clave] ?? 0 }}</span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-carbon-100"><div class="h-full rounded-full {{ $colores[$clave] }}" style="width: {{ round((($d['por_estado'][$clave] ?? 0) / $max) * 100) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </section>
            @isset($d['por_tecnico'])
            <section class="tarjeta">
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Carga por técnico</h3><span class="text-xs text-carbon-500">Órdenes abiertas</span></div>
                <div class="tarjeta-cuerpo space-y-3">
                    @php $maxT = max(1, $d['por_tecnico']->max('total')); @endphp
                    @forelse ($d['por_tecnico'] as $fila)
                        <div class="flex items-center gap-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-carbon-100 text-[11px] font-bold text-carbon-700">{{ $fila->responsable?->iniciales() }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex justify-between text-sm"><span class="truncate text-carbon-700">{{ $fila->responsable?->name }}</span><span class="font-semibold tabular-nums">{{ $fila->total }}</span></div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-carbon-100"><div class="h-full rounded-full bg-marca-600" style="width: {{ round($fila->total / $maxT * 100) }}%"></div></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-carbon-500">Sin órdenes asignadas.</p>
                    @endforelse
                </div>
            </section>
            @endisset
        </div>
        @endisset

        {{-- Últimos movimientos de bodega --}}
        @isset($d['ultimos_mov'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Últimos movimientos de bodega</h3>
                <a href="{{ route('movimientos.index') }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver todos</a>
            </div>
            @forelse ($d['ultimos_mov'] as $m)
                <a href="{{ route('movimientos.show', $m) }}" class="flex items-center gap-4 border-b border-carbon-50 px-5 py-3 transition last:border-0 hover:bg-carbon-50/70">
                    <x-movimiento.icono :efecto="$m->efecto" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-carbon-900">{{ $m->nombreTipo() }}</p>
                        <p class="truncate text-xs text-carbon-500">{{ $m->folio }} · {{ $m->user->name }} · {{ $m->created_at->diffForHumans() }}</p>
                    </div>
                    <x-movimiento.estado :estado="$m->estado" />
                </a>
            @empty
                <x-vacio icono="flechas" titulo="Sin movimientos todavía" />
            @endforelse
        </section>
        @endisset
    </div>

    {{-- Columna derecha --}}
    <div class="space-y-6">
        @isset($d['pendientes'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Ajustes por aprobar</h3>
                @if ($d['pendientes']->count())<span class="insignia-roja">{{ $d['pendientes']->count() }}</span>@endif
            </div>
            @forelse ($d['pendientes'] as $m)
                <a href="{{ route('movimientos.show', $m) }}" class="flex items-center gap-3 border-b border-carbon-50 px-5 py-3 transition last:border-0 hover:bg-carbon-50/70">
                    <x-movimiento.icono :efecto="$m->efecto" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $m->nombreTipo() }}</p>
                        <p class="truncate text-xs text-carbon-500">{{ $m->folio }} · {{ $m->user->name }}</p>
                    </div>
                    <x-icono n="derecha" clase="size-4 text-carbon-300" />
                </a>
            @empty
                <p class="px-5 py-6 text-center text-sm text-carbon-500">No hay ajustes pendientes.</p>
            @endforelse
        </section>
        @endisset

        @isset($d['bajo_minimo'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Bajo el mínimo</h3>
                <a href="{{ route('productos.index', ['bajo_minimo' => 1]) }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver</a>
            </div>
            @forelse ($d['bajo_minimo'] as $p)
                <a href="{{ route('productos.show', $p) }}" class="flex items-center justify-between gap-3 border-b border-carbon-50 px-5 py-3 transition last:border-0 hover:bg-carbon-50/70">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $p->nombre }}</p>
                        <p class="text-xs text-carbon-500">Mínimo @num($p->stock_minimo) {{ $p->unidad->abreviatura }}</p>
                    </div>
                    <span class="insignia-roja tabular-nums">@num($p->stockTotal())</span>
                </a>
            @empty
                <p class="flex items-center justify-center gap-2 px-5 py-6 text-sm text-carbon-500"><x-icono n="check-circulo" clase="size-4 text-emerald-500" /> Todo por encima del mínimo.</p>
            @endforelse
        </section>
        @endisset

        @isset($d['preventivos'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Preventivos próximos</h3>
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

        @isset($d['maquinas'])
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Máquinas</h3><a href="{{ route('maquinas.index') }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver</a></div>
            <div class="grid grid-cols-2 gap-px bg-carbon-100">
                @foreach (['operativa' => 'text-emerald-600', 'en_mantenimiento' => 'text-sky-600', 'fuera_servicio' => 'text-marca-600', 'baja' => 'text-carbon-400'] as $e => $color)
                    <a href="{{ route('maquinas.index', ['estado' => $e]) }}" class="bg-white px-5 py-4 transition hover:bg-carbon-50">
                        <p class="font-display text-2xl font-extrabold tabular-nums {{ $color }}">{{ $d['maquinas'][$e] ?? 0 }}</p>
                        <p class="text-xs font-medium text-carbon-500">{{ \App\Models\Maquina::ESTADOS[$e] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
        @endisset
    </div>
</div>
</x-layouts.app>
