<x-layouts.app titulo="Ocupación del personal">
@php
    $fmt = fn ($h) => \App\Support\Formato::duracion($h);
    [$colorEquipo, $textoEquipo] = \App\Support\Colores::ocupacion($k['ocupacion']);
@endphp
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-carbon-600">Del <b>@fecha($desde)</b> al <b>@fecha($hasta)</b></p>
    <x-grafica.periodo :desde="$desde" :hasta="$hasta" />
</div>

{{-- La respuesta en una frase --}}
<section class="tarjeta mb-6 flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
    <div class="shrink-0">
        <p class="dato-etiqueta">Ocupación del equipo</p>
        <p class="font-display text-5xl font-extrabold tracking-tight tabular-nums">{{ $k['ocupacion'] !== null ? $k['ocupacion'].'%' : '—' }}</p>
    </div>
    <div class="min-w-0 flex-1 sm:border-l sm:border-carbon-100 sm:pl-6">
        <p class="text-carbon-700">De <b>{{ $fmt($k['marcadas']) }}</b> que el personal estuvo marcado, <b>{{ $fmt($k['en_ot']) }}</b> fueron trabajo en órdenes.
            El resto es tiempo sin orden registrada: traslados, almuerzo, espera de repuestos o trabajo que no se cargó a una OT.</p>
        <x-ocupacion :pct="$k['ocupacion']" class="mt-3 max-w-md" />
    </div>
</section>

<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-kpi titulo="Llegadas tarde" :valor="$k['tardanzas']" icono="reloj" tono="ambar" :href="route('asistencia.index', ['ver' => 'tarde', 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()])" nota="Después de la tolerancia del turno" />
    <x-kpi titulo="Faltas" :valor="$k['faltas']" icono="alerta" tono="rojo" nota="Días de turno sin marcar" />
    <x-kpi titulo="Horas extra" :valor="\App\Support\Formato::numero($k['extra'], 1).' h'" icono="rayo" tono="azul" nota="Más allá de lo que dura su turno" />
    <x-kpi titulo="Salidas sin marcar" :valor="$k['sin_salida']" icono="salir" :href="route('asistencia.index', ['ver' => 'sin_salida', 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()])" nota="Cerradas por el sistema" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-5">
    {{-- Por técnico --}}
    <section class="tarjeta xl:col-span-3">
        <div class="tarjeta-cabeza">
            <div><h3 class="tarjeta-titulo">Ocupación por técnico</h3><p class="text-xs text-carbon-500">Horas en órdenes / horas marcadas</p></div>
            <div class="flex gap-3 text-[11px] text-carbon-500"><span>Buena ≥ 75 %</span><span>Media 50–74 %</span><span>Baja &lt; 50 %</span></div>
        </div>
        @if ($personas->isEmpty())
            <x-vacio icono="usuarios" titulo="Nadie marca asistencia" texto="Asigna un turno a cada técnico en Usuarios." />
        @else
        <div class="divide-y divide-carbon-50">
            @foreach ($personas as $p)
                <a href="{{ route('asistencia.index', ['persona' => $p->usuario->id, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]) }}" class="grid grid-cols-1 gap-2 px-5 py-3 transition hover:bg-carbon-50 sm:grid-cols-5 sm:items-center">
                    <div class="flex min-w-0 items-center gap-3 sm:col-span-2">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-carbon-900 text-xs font-bold text-white">{{ $p->usuario->iniciales() }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $p->usuario->name }}</p>
                            <p class="truncate text-xs text-carbon-500">{{ $fmt($p->en_ot) }} en órdenes de {{ $fmt($p->marcadas) }} · {{ $p->completadas }} {{ $p->completadas === 1 ? 'OT completada' : 'OT completadas' }}</p>
                        </div>
                    </div>
                    <x-ocupacion :pct="$p->ocupacion" class="sm:col-span-3" />
                </a>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Día por día --}}
    <section class="tarjeta xl:col-span-2">
        <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Día por día</h3><p class="text-xs text-carbon-500">Todo el personal</p></div></div>
        <div class="p-5">
            <x-grafica.columnas :datos="$dias->map(fn ($d) => ['etiqueta' => $d->fecha->format('d'), 'titulo' => ucfirst($d->fecha->translatedFormat('D d/m')), 'marcadas' => $d->marcadas, 'en_ot' => $d->en_ot])->all()"
                :series="['marcadas' => ['Horas marcadas', '#d6d1cb'], 'en_ot' => ['Horas en órdenes', '#2a78d6']]" sufijo=" h" />
        </div>
    </section>
</div>

{{-- Detalle --}}
<section class="tarjeta mt-6 overflow-hidden">
    <div class="tarjeta-cabeza">
        <h3 class="tarjeta-titulo">Detalle por persona</h3>
        @can('reportes.exportar')<a href="{{ route('asistencia.exportar', ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]) }}" class="btn-secundario btn-sm"><x-icono n="descargar" clase="size-4" /> Marcas en Excel</a>@endcan
    </div>
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Persona</th><th>Turno</th><th class="text-right">Días</th><th class="text-right">Faltas</th><th class="text-right">Marcado</th><th class="text-right">En órdenes</th><th class="text-right">Ocupación</th><th class="text-right">Tarde</th><th class="text-right">Salió antes</th><th class="text-right">Extra</th><th class="text-right">OT completadas</th></tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($personas as $p)
                <tr>
                    <td class="font-semibold text-carbon-900">{{ $p->usuario->name }}</td>
                    <td class="text-xs text-carbon-500">{{ $p->usuario->turno?->nombre ?? '—' }}</td>
                    <td class="tabla-num">{{ $p->dias }}</td>
                    <td class="tabla-num {{ $p->faltas ? 'font-semibold text-marca-700' : 'text-carbon-400' }}">{{ $p->faltas }}</td>
                    <td class="tabla-num">{{ $fmt($p->marcadas) }}</td>
                    <td class="tabla-num">{{ $fmt($p->en_ot) }}</td>
                    <td class="tabla-num font-bold">{{ $p->ocupacion !== null ? $p->ocupacion.'%' : '—' }}</td>
                    <td class="tabla-num">{{ $p->tardanzas ? $p->tardanzas.' · '.$p->min_tarde.' min' : '—' }}</td>
                    <td class="tabla-num">{{ $p->salidas_antes ? $p->salidas_antes.' · '.$p->min_antes.' min' : '—' }}</td>
                    <td class="tabla-num">{{ $p->extra ? \App\Support\Formato::numero($p->extra, 1).' h' : '—' }}</td>
                    <td class="tabla-num">{{ $p->completadas }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
</x-layouts.app>
