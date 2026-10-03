<x-layouts.app titulo="Reporte de mantenimiento">
@php
    $prevCorr = ($porTipo['preventivo'] ?? 0) + ($porTipo['correctivo'] ?? 0);
    $ratioPrev = $prevCorr ? (int) round(($porTipo['preventivo'] ?? 0) / $prevCorr * 100) : null;
    $tonoPct = fn ($p) => $p === null ? 'carbon' : ($p >= 80 ? 'verde' : ($p >= 60 ? 'ambar' : 'rojo'));
@endphp
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-carbon-600">Del <b>@fecha($desde)</b> al <b>@fecha($hasta)</b></p>
    <x-grafica.periodo :desde="$desde" :hasta="$hasta" />
</div>

{{-- Resumen en palabras --}}
<section class="tarjeta mb-6 p-5 sm:p-6">
    @php
        $frase = 'Se completaron <b class="text-carbon-900">'.$k['completadas'].' '.($k['completadas'] === 1 ? 'orden' : 'órdenes').'</b>'
            .($k['cumplimiento'] !== null ? ', <b class="text-carbon-900">'.$k['cumplimiento'].' %</b> antes de su fecha de vencimiento' : '').'. '
            .'Hoy hay <b class="text-carbon-900">'.$k['abiertas'].' abiertas</b>'
            .($k['atrasadas'] ? ', de las cuales <a href="'.route('ot.index', ['situacion' => 'atrasada']).'" class="font-bold text-marca-700 hover:underline">'.$k['atrasadas'].' están atrasadas</a>' : '').'. '
            .($ratioPrev !== null ? 'El <b class="text-carbon-900">'.$ratioPrev.' %</b> del trabajo fue preventivo'.($ratioPrev >= 70 ? ', dentro de la meta.' : ' (la meta usual es más de 70 %).') : '');
    @endphp
    <p class="text-lg leading-relaxed text-carbon-700">{!! $frase !!}</p>
</section>

<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-kpi titulo="Órdenes completadas" :valor="$k['completadas']" icono="check-circulo" tono="verde" :nota="$k['creadas'].' creadas en el período'" />
    <x-kpi titulo="Completadas a tiempo" :valor="$k['cumplimiento'] !== null ? $k['cumplimiento'].'%' : '—'" icono="reloj" :tono="$tonoPct($k['cumplimiento'])" nota="Antes de su vencimiento" />
    <x-kpi titulo="Preventivo a tiempo" :valor="$k['preventivo'] !== null ? $k['preventivo'].'%' : '—'" icono="repetir" :tono="$tonoPct($k['preventivo'])" nota="Cumplimiento del plan preventivo" />
    <x-kpi titulo="Paro de máquinas" :valor="\App\Support\Formato::numero($k['paro'], 1).' h'" icono="alerta" :tono="$k['paro'] ? 'rojo' : 'carbon'" :nota="$k['mttr'] !== null ? 'Reparación promedio (MTTR): '.$k['mttr'].' h' : 'Sin paros registrados'" />
</div>

{{-- ¿Cómo vamos? --}}
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <section class="tarjeta xl:col-span-2">
        <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Órdenes por semana</h3><p class="text-xs text-carbon-500">Si las completadas quedan por debajo de las creadas, el trabajo pendiente crece.</p></div></div>
        <div class="p-5">
            <x-grafica.columnas :datos="$semanas->map(fn ($s) => $s + ['titulo' => 'Semana del '.$s['etiqueta']])->all()"
                :series="['creadas' => ['Creadas', '#d6d1cb'], 'completadas' => ['Completadas', '#2a78d6']]" />
        </div>
    </section>

    <div class="space-y-6">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Abiertas hoy</h3><span class="text-sm font-bold tabular-nums">{{ $k['abiertas'] }}</span></div>
            <div class="p-5">
                <x-grafica.apilada :datos="collect(['pendiente', 'en_progreso', 'en_espera'])->map(fn ($e) => ['etiqueta' => \App\Models\OrdenTrabajo::ESTADOS[$e], 'valor' => $abiertasPorEstado[$e] ?? 0, 'color' => \App\Support\Colores::ESTADOS_OT[$e], 'href' => route('ot.index', ['estado' => $e])])->all()" />
            </div>
        </section>
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Máquinas hoy</h3><a href="{{ route('maquinas.index') }}" class="text-sm font-semibold text-marca-700 hover:underline">Ver</a></div>
            <div class="grid grid-cols-3 divide-x divide-carbon-100 text-center">
                @foreach (['operativa' => ['Operativas', 'text-emerald-600'], 'en_mantenimiento' => ['En mantenimiento', 'text-sky-700'], 'fuera_servicio' => ['Fuera de servicio', 'text-marca-700']] as $e => [$t, $c])
                    <a href="{{ route('maquinas.index', ['estado' => $e]) }}" class="px-2 py-4 transition hover:bg-carbon-50"><p class="font-display text-2xl font-extrabold tabular-nums {{ $c }}">{{ $estadoMaquinas[$e] ?? 0 }}</p><p class="text-xs text-carbon-500">{{ $t }}</p></a>
                @endforeach
            </div>
        </section>
    </div>
</div>

{{-- ¿Qué se hizo? --}}
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Qué tipo de trabajo se hizo</h3><p class="text-xs text-carbon-500">Órdenes completadas</p></div></div>
        <div class="p-5">
            <x-grafica.dona nota="completadas" :datos="collect(\App\Models\OrdenTrabajo::TIPOS)->map(fn ($n, $t) => ['etiqueta' => $n, 'valor' => $porTipo[$t] ?? 0, 'color' => \App\Support\Colores::TIPOS_OT[$t]])->values()->all()" />
        </div>
    </section>
    <section class="tarjeta">
        <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Por especialidad</h3><p class="text-xs text-carbon-500">Órdenes completadas</p></div></div>
        <div class="p-5"><x-grafica.barras :datos="$porEspecialidad->map(fn ($n, $e) => ['etiqueta' => $e, 'valor' => $n])->values()->all()" color="bg-carbon-700" /></div>
    </section>
</div>

{{-- ¿Quién lo hizo? --}}
<section class="tarjeta mt-6 overflow-hidden">
    <div class="tarjeta-cabeza">
        <div><h3 class="tarjeta-titulo">Técnicos</h3><p class="text-xs text-carbon-500">Órdenes completadas en el período como responsable</p></div>
        @can('asistencia.ver')<a href="{{ route('reportes.ocupacion', ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]) }}" class="btn-secundario btn-sm"><x-icono n="usuarios" clase="size-4" /> Ocupación del personal</a>@endcan
    </div>
    @if ($porTecnico->isEmpty())
        <p class="py-8 text-center text-sm text-carbon-500">Sin órdenes completadas en el período.</p>
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Técnico</th><th class="text-right">Completadas</th><th class="text-right">A tiempo</th><th class="text-right">Correctivas</th><th class="text-right">Horas</th><th class="text-right">Abiertas hoy</th>@can('asistencia.ver')<th class="w-64">Ocupación</th>@endcan</tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($porTecnico as $t)
                <tr>
                    <td><div class="flex items-center gap-2.5"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-carbon-900 text-[11px] font-bold text-white">{{ $t->usuario->iniciales() }}</span><span class="font-semibold text-carbon-900">{{ $t->usuario->name }}</span></div></td>
                    <td class="tabla-num font-bold">{{ $t->completadas }}</td>
                    <td class="tabla-num"><span class="{{ $t->a_tiempo >= 80 ? 'insignia-verde' : ($t->a_tiempo >= 60 ? 'insignia-ambar' : 'insignia-roja') }}">{{ $t->a_tiempo }} %</span></td>
                    <td class="tabla-num">{{ $t->correctivas }}</td>
                    <td class="tabla-num">{{ \App\Support\Formato::duracion($t->horas) }}</td>
                    <td class="tabla-num"><a href="{{ route('ot.index', ['responsable' => $t->usuario->id]) }}" class="hover:text-marca-700">{{ $t->abiertas }}</a></td>
                    @can('asistencia.ver')<td>@if ($t->usuario->turno_id)<x-ocupacion :pct="$t->ocupacion" :detalle="false" />@else<span class="text-xs text-carbon-400">No marca asistencia</span>@endif</td>@endcan
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>

{{-- ¿Dónde? --}}
<section class="tarjeta mt-6 overflow-hidden">
    <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Máquinas que más mantenimiento pidieron</h3><p class="text-xs text-carbon-500">Las que más correctivos tuvieron primero</p></div></div>
    @if ($porMaquina->isEmpty())
        <p class="py-8 text-center text-sm text-carbon-500">Sin trabajos en el período.</p>
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Máquina</th><th class="text-right">Correctivos</th><th class="text-right">Preventivos</th><th class="text-right">Horas</th><th class="text-right">Paro</th>@if ($costos)<th class="text-right">Servicios externos</th><th class="text-right">Repuestos</th>@endif</tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($porMaquina as $r)
                @php $m = $maquinas[$r->maquina_id] ?? null; @endphp
                <tr>
                    <td>@if ($m)<a href="{{ route('maquinas.show', ['maquina' => $m, 'tab' => 'bitacora']) }}" class="font-semibold text-carbon-900 hover:text-marca-700">{{ $m->etiqueta() }}</a>@endif</td>
                    <td class="tabla-num font-semibold {{ $r->correctivos > 2 ? 'text-marca-700' : '' }}">{{ $r->correctivos }}</td>
                    <td class="tabla-num">{{ $r->preventivos }}</td>
                    <td class="tabla-num">{{ \App\Support\Formato::numero($r->horas, 1) }} h</td>
                    <td class="tabla-num">{{ isset($paroPorMaquina[$r->maquina_id]) ? \App\Support\Formato::numero($paroPorMaquina[$r->maquina_id], 1).' h' : '—' }}</td>
                    @if ($costos)
                        <td class="tabla-num">@dinero($r->costo_externo)</td>
                        <td class="tabla-num">@dinero($repuestos[$r->maquina_id] ?? 0)</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>
</x-layouts.app>
