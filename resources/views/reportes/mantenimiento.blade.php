<x-layouts.app titulo="Reporte de mantenimiento">
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-carbon-600">Del <b>@fecha($desde)</b> al <b>@fecha($hasta)</b></p>
    <x-grafica.periodo :desde="$desde" :hasta="$hasta" />
</div>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-kpi titulo="Órdenes completadas" :valor="$k['completadas']" icono="check-circulo" tono="verde" :nota="$k['creadas'].' creadas en el período'" />
    <x-kpi titulo="Cumplimiento a tiempo" :valor="$k['cumplimiento'] !== null ? $k['cumplimiento'].'%' : '—'" icono="reloj" :tono="($k['cumplimiento'] ?? 100) >= 80 ? 'verde' : 'ambar'" nota="Completadas antes del vencimiento" />
    <x-kpi titulo="Pendientes hoy" :valor="$k['abiertas']" icono="portapapeles" :nota="$k['atrasadas'].' atrasadas'" :tono="$k['atrasadas'] ? 'rojo' : 'carbon'" />
    <x-kpi titulo="Horas de mantenimiento" :valor="\App\Support\Formato::numero($k['horas'], 1)" icono="llave" tono="azul" :nota="'Paro de máquinas: '.\App\Support\Formato::numero($k['paro'], 1).' h'.($k['mttr'] !== null ? ' · MTTR '.$k['mttr'].' h' : '')" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    {{-- Tendencia --}}
    <section class="tarjeta xl:col-span-2">
        <div class="tarjeta-cabeza">
            <h3 class="tarjeta-titulo">Órdenes por semana</h3>
            <div class="flex gap-3 text-xs text-carbon-600"><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-carbon-300"></span>Creadas</span><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-marca-600"></span>Completadas</span></div>
        </div>
        @php $maxS = max(1, $semanas->max('creadas'), $semanas->max('completadas')); @endphp
        <div class="flex h-56 items-end gap-1.5 overflow-x-auto px-5 pt-6 pb-2">
            @foreach ($semanas as $s)
                <div class="group flex min-w-7 flex-1 flex-col items-center gap-1">
                    <div class="flex h-44 w-full items-end justify-center gap-0.5">
                        <div class="w-1/2 rounded-t bg-carbon-300 transition-all group-hover:bg-carbon-400" style="height: {{ $s['creadas'] / $maxS * 100 }}%" title="{{ $s['creadas'] }} creadas"></div>
                        <div class="w-1/2 rounded-t bg-marca-600 transition-all group-hover:bg-marca-700" style="height: {{ $s['completadas'] / $maxS * 100 }}%" title="{{ $s['completadas'] }} completadas"></div>
                    </div>
                    <span class="text-[10px] text-carbon-400">{{ $s['etiqueta'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Por tipo --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Trabajos por tipo</h3></div>
        @php
            $total = max(1, $porTipo->sum());
            $colores = ['preventivo' => '#10b981', 'correctivo' => '#d90016', 'predictivo' => '#8b5cf6', 'mejora' => '#0ea5e9', 'proyecto' => '#f59e0b'];
            $acum = 0;
        @endphp
        <div class="flex items-center gap-6 p-5">
            <svg viewBox="0 0 42 42" class="size-36 shrink-0 -rotate-90">
                <circle cx="21" cy="21" r="15.9" fill="none" stroke="#efece8" stroke-width="6"/>
                @foreach ($porTipo as $t => $n)
                    @php $pct = $n / $total * 100; @endphp
                    <circle cx="21" cy="21" r="15.9" fill="none" stroke="{{ $colores[$t] ?? '#a39c94' }}" stroke-width="6" stroke-dasharray="{{ $pct }} {{ 100 - $pct }}" stroke-dashoffset="{{ -$acum }}"/>
                    @php $acum += $pct; @endphp
                @endforeach
            </svg>
            <ul class="space-y-2 text-sm">
                @forelse ($porTipo as $t => $n)
                    <li class="flex items-center gap-2"><span class="size-2.5 rounded-sm" style="background: {{ $colores[$t] ?? '#a39c94' }}"></span>{{ \App\Models\OrdenTrabajo::TIPOS[$t] ?? $t }} <b class="ml-auto pl-3 tabular-nums">{{ $n }}</b></li>
                @empty
                    <li class="text-carbon-500">Sin trabajos en el período.</li>
                @endforelse
            </ul>
        </div>
        @if ($porTipo->sum())
            <p class="border-t border-carbon-100 px-5 py-3 text-xs text-carbon-500">Preventivo sobre correctivo: <b class="text-carbon-800">{{ round(($porTipo['preventivo'] ?? 0) / max(1, ($porTipo['preventivo'] ?? 0) + ($porTipo['correctivo'] ?? 0)) * 100) }}%</b> (la meta usual es más de 70 %).</p>
        @endif
    </section>

    {{-- Técnicos --}}
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Horas por técnico</h3></div>
        <div class="p-5"><x-grafica.barras :datos="$porTecnico->map(fn ($t) => ['etiqueta' => $t['nombre'], 'valor' => $t['horas'], 'nota' => $t['trabajos'].' trabajos'])->all()" sufijo=" h" /></div>
    </section>

    {{-- Máquinas --}}
    <section class="tarjeta overflow-hidden xl:col-span-2">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Máquinas con más correctivos</h3></div>
        @if ($porMaquina->isEmpty())
            <p class="py-8 text-center text-sm text-carbon-500">Sin trabajos en el período.</p>
        @else
        <table class="tabla">
            <thead><tr><th>Máquina</th><th class="text-right">Correctivos</th><th class="text-right">Preventivos</th>@if ($costos)<th class="text-right">Servicios externos</th><th class="text-right">Repuestos</th>@endif</tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($porMaquina as $r)
                @php $m = $maquinas[$r->maquina_id] ?? null; @endphp
                <tr>
                    <td>@if ($m)<a href="{{ route('maquinas.show', ['maquina' => $m, 'tab' => 'bitacora']) }}" class="font-semibold text-carbon-900 hover:text-marca-700">{{ $m->etiqueta() }}</a>@endif</td>
                    <td class="tabla-num font-semibold {{ $r->correctivos > 2 ? 'text-marca-700' : '' }}">{{ $r->correctivos }}</td>
                    <td class="tabla-num">{{ $r->preventivos }}</td>
                    @if ($costos)
                        <td class="tabla-num">@dinero($r->costo_externo)</td>
                        <td class="tabla-num">@dinero($repuestos[$r->maquina_id] ?? 0)</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </section>
</div>
</x-layouts.app>
