<x-layouts.app :titulo="$maquina->nombre">
<x-slot:migas><a href="{{ route('maquinas.index') }}" class="hover:text-carbon-800">Máquinas</a><x-icono n="derecha" clase="size-3" />{{ $maquina->codigo ?? 'Ficha' }}</x-slot:migas>

@php $tab = request('tab', 'ficha'); @endphp
<div x-data="{ tab: '{{ $tab }}' }" x-init="$watch('tab', t => history.replaceState(null, '', '?tab=' + t))">

{{-- Encabezado --}}
<div class="tarjeta overflow-hidden">
    <div class="flex flex-col gap-4 p-4 max-md:block max-md:space-y-4 max-md:after:clear-both max-md:after:table md:flex-row md:items-center md:gap-5 md:p-5">
        @if ($maquina->foto)
            <img src="{{ Storage::url($maquina->foto) }}" alt="" class="size-16 shrink-0 rounded-xl object-cover ring-1 ring-carbon-200 max-md:float-left max-md:mr-4 md:size-24">
        @else
            <span class="grid size-16 shrink-0 place-items-center rounded-xl bg-carbon-100 text-carbon-400 max-md:float-left max-md:mr-4 md:size-24"><x-icono n="maquina" clase="size-8 md:size-10" /></span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                @if ($maquina->codigo)<span class="insignia-oscura font-mono">{{ $maquina->codigo }}</span>@endif
                <x-maquina.estado :estado="$maquina->estado" />
                <span class="insignia-gris">Criticidad {{ $maquina->criticidad }}</span>
            </div>
            <h2 class="mt-2 font-display text-xl font-extrabold tracking-tight md:text-2xl">{{ $maquina->nombre }}</h2>
            <p class="mt-1 text-sm text-carbon-500">
                {{ $maquina->area?->nombre ?? 'Sin área' }}
                @if ($maquina->marca) · {{ $maquina->marca }} @endif
                @if ($maquina->modelo) · Modelo {{ $maquina->modelo }} @endif
                @if ($maquina->serie) · Serie {{ $maquina->serie }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 md:justify-end">
            @can('ot.crear')
                <a href="{{ route('ot.create', ['maquina' => $maquina->id]) }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Orden de trabajo</a>
            @endcan
            @can('bitacora.crear')
                <a href="{{ route('bitacora.create', ['maquina' => $maquina->id]) }}" class="btn-secundario"><x-icono n="libro" clase="size-4" /> Registrar en bitácora</a>
            @endcan
            @can('maquinas.editar')
                <a href="{{ route('maquinas.edit', $maquina) }}" class="btn-secundario"><x-icono n="lapiz" clase="size-4" /> Editar</a>
            @endcan
        </div>
    </div>
    <div class="grid grid-cols-2 divide-x divide-y divide-carbon-100 border-t border-carbon-100 md:grid-cols-4 md:divide-y-0">
        <div class="px-5 py-3.5"><p class="dato-etiqueta">OT abiertas</p><p class="font-display text-xl font-extrabold">{{ $stats['ot_abiertas'] }}</p></div>
        <div class="px-5 py-3.5"><p class="dato-etiqueta">Correctivos {{ now()->year }}</p><p class="font-display text-xl font-extrabold">{{ $stats['correctivos_anio'] }}</p></div>
        <div class="px-5 py-3.5"><p class="dato-etiqueta">Horas de mantenimiento {{ now()->year }}</p><p class="font-display text-xl font-extrabold">@num($stats['horas_anio'])</p></div>
        <div class="px-5 py-3.5"><p class="dato-etiqueta">Último mantenimiento</p><p class="font-display text-xl font-extrabold">@fecha($stats['ultimo'])</p></div>
    </div>
</div>

{{-- Pestañas --}}
<div class="pestanas mt-6">
    @foreach (['ficha' => 'Ficha técnica', 'bitacora' => 'Bitácora ('.$bitacora->count().')', 'ordenes' => 'Órdenes de trabajo ('.$ordenes->count().')', 'planes' => 'Planes preventivos ('.$maquina->planes->count().')', 'archivos' => 'Documentos ('.$maquina->archivos->count().')'] as $k => $v)
        <button class="pestana" :class="tab === '{{ $k }}' && 'activa'" @click="tab = '{{ $k }}'">{{ $v }}</button>
    @endforeach
</div>

{{-- Ficha técnica --}}
<div x-show="tab === 'ficha'" class="mt-6 space-y-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="tarjeta lg:col-span-1">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Datos generales</h3></div>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-4 p-5">
                @foreach (['No.' => $maquina->numero, 'Código' => $maquina->codigo, 'Marca' => $maquina->marca, 'Modelo' => $maquina->modelo, 'Serie' => $maquina->serie, 'Año' => $maquina->anio, 'Ubicación' => $maquina->ubicacion, 'Horómetro' => $maquina->horometro ? \App\Support\Formato::numero($maquina->horometro, 1).' h' : null] as $k => $v)
                    <div><dt class="dato-etiqueta">{{ $k }}</dt><dd class="dato-valor">{{ $v ?? '—' }}</dd></div>
                @endforeach
                <div class="col-span-2"><dt class="dato-etiqueta">Observaciones</dt><dd class="dato-valor whitespace-pre-line">{{ $maquina->observaciones ?? '—' }}</dd></div>
            </dl>
        </section>
        <div class="space-y-6 lg:col-span-2">
            @forelse ($maquina->componentes as $c)
                <section class="tarjeta overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-carbon-100 bg-carbon-50/60 px-5 py-3">
                        <span class="grid size-8 place-items-center rounded-lg bg-white text-marca-600 ring-1 ring-carbon-200"><x-icono n="engrane" clase="size-4" /></span>
                        <h3 class="tarjeta-titulo">{{ $c->nombre }}</h3>
                    </div>
                    <dl class="grid grid-cols-1 gap-px bg-carbon-100 sm:grid-cols-2">
                        @foreach ($c->especificaciones ?? [] as $s)
                            <div class="flex items-baseline justify-between gap-4 bg-white px-5 py-2.5">
                                <dt class="text-sm text-carbon-500">{{ $s['clave'] }}</dt>
                                <dd class="text-right text-sm font-semibold text-carbon-900">{{ $s['valor'] }}</dd>
                            </div>
                        @endforeach
                        @if (count($c->especificaciones ?? []) % 2) <div class="hidden bg-white sm:block"></div> @endif
                    </dl>
                </section>
            @empty
                <section class="tarjeta">
                    <x-vacio icono="engrane" titulo="Sin ficha técnica" texto="Agrega los componentes de la máquina (motor, bomba, unidad hidráulica…) con sus especificaciones.">
                        @can('maquinas.editar')<a href="{{ route('maquinas.edit', $maquina) }}#ficha" class="btn-secundario"><x-icono n="mas" clase="size-4" /> Agregar ficha técnica</a>@endcan
                    </x-vacio>
                </section>
            @endforelse

            @if ($maquina->partes->isNotEmpty())
            <section class="tarjeta overflow-hidden">
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Partes y consumibles</h3></div>
                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead><tr><th>Grupo</th><th>Especificación</th><th>Dimensiones</th><th class="text-right">Cantidad</th><th>En bodega</th></tr></thead>
                        <tbody class="divide-y divide-carbon-50">
                        @foreach ($maquina->partes as $p)
                            <tr>
                                <td>{{ $p->grupo ?? '—' }}</td>
                                <td class="font-medium text-carbon-900">{{ $p->especificacion }}</td>
                                <td>{{ $p->dimensiones ?? '—' }}</td>
                                <td class="tabla-num">@num($p->cantidad)</td>
                                <td>
                                    @if ($p->producto)
                                        @php $stock = (float) $p->producto->existencia; @endphp
                                        @can('inventario.ver')
                                        <a href="{{ route('productos.show', $p->producto) }}" class="{{ $stock > 0 ? 'insignia-verde' : 'insignia-roja' }}">@num($stock) disponibles</a>
                                        @else
                                        <span class="{{ $stock > 0 ? 'insignia-verde' : 'insignia-roja' }}">@num($stock) disponibles</span>
                                        @endcan
                                    @else
                                        <span class="text-xs text-carbon-400">No vinculado</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @endif
        </div>
    </div>
</div>

{{-- Bitácora --}}
<div x-show="tab === 'bitacora'" x-cloak class="mt-6">
    <div class="tarjeta">
        <div class="tarjeta-cabeza">
            <h3 class="tarjeta-titulo">Registro de mantenimiento</h3>
            <div class="flex flex-wrap gap-2">
                @can('reportes.exportar')<a href="{{ route('bitacora.exportar', ['maquina' => $maquina->id]) }}" class="btn-secundario btn-sm"><x-icono n="descargar" clase="size-4" /> Excel</a>@endcan
                @can('bitacora.crear')<a href="{{ route('bitacora.create', ['maquina' => $maquina->id]) }}" class="btn-oscuro btn-sm"><x-icono n="mas" clase="size-4" /> Registrar</a>@endcan
            </div>
        </div>
        @include('bitacora._linea_tiempo', ['registros' => $bitacora, 'mostrarMaquina' => false])
    </div>
</div>

{{-- Órdenes --}}
<div x-show="tab === 'ordenes'" x-cloak class="mt-6">
    <div class="tarjeta overflow-hidden">
        @if ($ordenes->isEmpty())
            <x-vacio icono="portapapeles" titulo="Sin órdenes de trabajo" texto="Crea una orden para asignar trabajo a un técnico sobre esta máquina." />
        @else
        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Folio</th><th>Trabajo</th><th>Tipo</th><th>Responsable</th><th>Vence</th><th>Estado</th><th class="text-right">Avance</th></tr></thead>
                <tbody class="divide-y divide-carbon-50">
                @foreach ($ordenes as $ot)
                    <tr class="cursor-pointer" onclick="location='{{ route('ot.show', $ot) }}'">
                        <td class="font-mono text-xs font-semibold">{{ $ot->folio }}</td>
                        <td class="max-w-sm truncate font-semibold text-carbon-900">{{ $ot->titulo }}</td>
                        <td>{{ \App\Models\OrdenTrabajo::TIPOS[$ot->tipo] }}</td>
                        <td>{{ $ot->responsable?->name ?? 'Sin asignar' }}</td>
                        <td class="whitespace-nowrap">@fecha($ot->fecha_vencimiento)</td>
                        <td><x-ot.estado :estado="$ot->estado" /></td>
                        <td class="tabla-num font-semibold">{{ $ot->progreso }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Planes --}}
<div x-show="tab === 'planes'" x-cloak class="mt-6">
    <div class="tarjeta overflow-hidden">
        <div class="tarjeta-cabeza">
            <h3 class="tarjeta-titulo">Mantenimiento preventivo programado</h3>
            @can('planes.gestionar')<a href="{{ route('planes.create', ['maquina' => $maquina->id]) }}" class="btn-oscuro btn-sm"><x-icono n="mas" clase="size-4" /> Nuevo plan</a>@endcan
        </div>
        @forelse ($maquina->planes as $p)
            <div class="flex items-center gap-4 border-b border-carbon-50 px-5 py-3.5 last:border-0">
                <span class="grid size-10 place-items-center rounded-lg {{ $p->activo ? 'bg-emerald-50 text-emerald-600' : 'bg-carbon-100 text-carbon-400' }}"><x-icono n="repetir" clase="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $p->titulo }}</p>
                    <p class="text-xs text-carbon-500">{{ $p->frecuenciaTexto() }} · {{ $p->responsable?->name ?? 'Sin responsable' }}</p>
                </div>
                <div class="text-right">
                    <p class="dato-etiqueta">Próxima</p>
                    <p class="text-sm font-semibold">@fecha($p->proxima_fecha)</p>
                </div>
                @can('planes.gestionar')<a href="{{ route('planes.edit', $p) }}" class="btn-fantasma btn-sm"><x-icono n="lapiz" clase="size-4" /></a>@endcan
            </div>
        @empty
            <x-vacio icono="repetir" titulo="Sin planes preventivos" texto="Programa tareas que se repiten (lubricación, revisión eléctrica…) y el sistema creará las órdenes solo." />
        @endforelse
    </div>
</div>

{{-- Documentos --}}
<div x-show="tab === 'archivos'" x-cloak class="mt-6">
    <div class="tarjeta tarjeta-cuerpo">
        <x-archivos :modelo="$maquina" tipo="maquina" :puede-subir="auth()->user()->can('maquinas.editar')" :categorias="\App\Models\Archivo::CATEGORIAS" />
    </div>
</div>
</div>
</x-layouts.app>
