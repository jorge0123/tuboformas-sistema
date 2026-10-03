<x-layouts.app :titulo="$ot->folio">
@php
    $yo = auth()->user();
    $marca = $yo->marcaAsistencia();
    $enTurno = $marca ? $yo->asistenciaAbierta() : null;
    $miTramo = $ot->tramos->first(fn ($t) => ! $t->fin_at && $t->user_id === $yo->id);
    $tiempo = $ot->tramos->sum(fn ($t) => $t->horas());
@endphp
<x-slot:migas><a href="{{ route('ot.index') }}" class="hover:text-carbon-800">Órdenes de trabajo</a><x-icono n="derecha" clase="size-3" />{{ $ot->folio }}</x-slot:migas>

{{-- Encabezado --}}
<div class="tarjeta p-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-sm font-bold text-carbon-400">{{ $ot->folio }}</span>
                <x-ot.estado :estado="$ot->estado" />
                <x-ot.situacion :ot="$ot" />
                <span class="insignia-gris">{{ \App\Models\OrdenTrabajo::TIPOS[$ot->tipo] }}</span>
                <x-ot.prioridad :prioridad="$ot->prioridad" class="ml-1" />
            </div>
            <h2 class="mt-2 font-display text-xl leading-tight font-extrabold tracking-tight sm:text-2xl">{{ $ot->titulo }}</h2>
            @if ($ot->maquina)
                <a href="{{ route('maquinas.show', $ot->maquina) }}" class="mt-1.5 inline-flex items-center gap-1.5 text-sm font-semibold text-marca-700 hover:underline">
                    <x-icono n="maquina" clase="size-4" />{{ $ot->maquina->etiqueta() }}<span class="font-normal text-carbon-500">· {{ $ot->maquina->area?->nombre }}</span>
                </a>
            @else
                <p class="mt-1.5 text-sm text-carbon-500">Tarea general (sin máquina)</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($permisos['ejecutar'] && $ot->estaAbierta())
                <button class="btn-exito max-sm:h-11 max-sm:w-full" @click="$dispatch('abrir-modal', 'completar')"><x-icono n="check-circulo" clase="size-4" /> Completar orden</button>
            @endif
            @if ($permisos['editar'])
                <a href="{{ route('ot.edit', $ot) }}" class="btn-secundario"><x-icono n="lapiz" clase="size-4" /> Editar</a>
            @endif
            @if ($permisos['cancelar'])
                <button class="btn-peligro" @click="$dispatch('abrir-modal', 'cancelar')"><x-icono n="x" clase="size-4" /> Cancelar</button>
            @endif
        </div>
    </div>
    {{-- En celular lo esencial queda arriba: quién la tiene y cuándo vence (el detalle completo está al final). --}}
    <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-carbon-50 p-3 text-sm ring-1 ring-carbon-100 xl:hidden">
        <div class="min-w-0">
            <dt class="text-[11px] font-semibold tracking-wide text-carbon-400 uppercase">Responsable</dt>
            <dd class="truncate font-semibold text-carbon-900">{{ $ot->responsable?->name ?? 'Sin asignar' }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[11px] font-semibold tracking-wide text-carbon-400 uppercase">Vence</dt>
            <dd class="font-semibold {{ in_array($ot->situacion(), ['atrasada', 'completada_tarde']) ? 'text-marca-700' : 'text-carbon-900' }}">{{ $ot->fecha_vencimiento?->translatedFormat('d M Y') ?? 'Sin fecha' }}</dd>
        </div>
    </dl>
    <div class="mt-5">
        <div class="mb-1.5 flex items-center justify-between text-sm">
            <span class="font-medium text-carbon-600">Avance</span>
            <span class="font-display font-extrabold tabular-nums">{{ $ot->progreso }}%</span>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-carbon-100">
            <div class="h-full rounded-full transition-all {{ $ot->estado === 'completada' ? 'bg-emerald-500' : 'bg-marca-600' }}" style="width: {{ $ot->progreso }}%"></div>
        </div>
        @if ($ot->estado === 'en_espera' && $ot->motivo_espera)
            <p class="mt-3 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200"><x-icono n="pausa" clase="size-4" /> En espera: {{ $ot->motivo_espera }}</p>
        @endif
        @if ($ot->bitacora)
            <a href="{{ route('maquinas.show', ['maquina' => $ot->maquina_id, 'tab' => 'bitacora']) }}" class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900 ring-1 ring-emerald-200 hover:bg-emerald-100">
                <x-icono n="libro" clase="size-4" /> Registrada en la bitácora de la máquina el @fecha($ot->bitacora->fecha). <span class="underline">Ver bitácora</span>
            </a>
        @endif
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">

        {{-- Registrar avance (tracking) --}}
        {{-- Tiempo laboral: corre solo mientras el técnico está marcado y trabajando en esta orden --}}
        @if ($ot->tramos->isNotEmpty() || ($permisos['ejecutar'] && $ot->estaAbierta() && $marca))
        <section class="tarjeta overflow-hidden">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <span class="grid size-12 shrink-0 place-items-center rounded-xl {{ $miTramo ? 'bg-emerald-50 text-emerald-600' : 'bg-carbon-100 text-carbon-500' }}"><x-icono n="reloj" clase="size-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="dato-etiqueta">Tiempo laboral en esta orden</p>
                    <p class="font-display text-2xl font-extrabold tabular-nums">{{ \App\Support\Formato::duracion($tiempo) }}</p>
                    <p class="text-xs text-carbon-500">
                        @if ($miTramo) Tu tiempo corre desde las {{ $miTramo->inicio_at->format('H:i') }}. Se detiene al pausar, poner la orden en espera o marcar salida.
                        @elseif ($marca && ! $enTurno && $ot->estaAbierta()) Marca tu entrada para trabajar en esta orden.
                        @else Solo cuenta el tiempo dentro del turno de cada técnico. @endif
                    </p>
                </div>
                @if ($permisos['ejecutar'] && $ot->estaAbierta() && $marca)
                    @if ($miTramo)
                        <form method="POST" action="{{ route('ot.pausar', $ot) }}">@csrf<button class="btn-secundario max-sm:w-full"><x-icono n="pausa" clase="size-4" /> Pausar mi tiempo</button></form>
                    @elseif ($enTurno)
                        <form method="POST" action="{{ route('ot.trabajar', $ot) }}">@csrf<button class="btn-exito max-sm:w-full"><x-icono n="play" clase="size-4" /> Trabajar en esta orden</button></form>
                    @else
                        <form method="POST" action="{{ route('asistencia.entrada') }}">@csrf<button class="btn-primario max-sm:w-full"><x-icono n="reloj" clase="size-4" /> Marcar entrada</button></form>
                    @endif
                @endif
            </div>
            @if ($ot->tramos->isNotEmpty())
            <details class="border-t border-carbon-100">
                <summary class="cursor-pointer px-5 py-2.5 text-xs font-semibold text-carbon-600 hover:bg-carbon-50">Ver detalle por persona y día ({{ $ot->tramos->count() }} {{ $ot->tramos->count() === 1 ? 'tramo' : 'tramos' }})</summary>
                <div class="divide-y divide-carbon-50">
                    @foreach ($ot->tramos->groupBy('user_id') as $tramos)
                        <div class="px-5 py-3">
                            <p class="flex justify-between text-sm font-semibold"><span>{{ $tramos->first()->user->name }}</span><span class="tabular-nums">{{ \App\Support\Formato::duracion($tramos->sum(fn ($t) => $t->horas())) }}</span></p>
                            <ul class="mt-1 space-y-0.5 text-xs text-carbon-500">
                                @foreach ($tramos as $t)
                                    <li class="flex justify-between gap-3"><span class="tabular-nums">{{ ucfirst($t->inicio_at->translatedFormat('D d/m')) }} · {{ $t->inicio_at->format('H:i') }} – {{ $t->fin_at?->format('H:i') ?? 'ahora' }}</span>
                                        <span>{{ $t->fin_at ? (\App\Models\OtTramo::CIERRES[$t->cierre] ?? '') : 'En curso' }} · {{ \App\Support\Formato::duracion($t->horas()) }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </details>
            @endif
        </section>
        @endif

        @if ($permisos['ejecutar'] && $ot->estaAbierta())
        <section class="tarjeta" x-data="{ estado: '{{ $ot->estado === 'pendiente' ? 'en_progreso' : $ot->estado }}', progreso: {{ max($ot->progreso, $ot->estado === 'pendiente' ? 10 : 0) }} }">
            <div class="tarjeta-cabeza">
                <div>
                    <h3 class="tarjeta-titulo">Registrar avance</h3>
                    <p class="text-xs text-carbon-500">Cada registro queda en el seguimiento y se notifica a quien asignó la orden.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('ot.seguimiento', $ot) }}" class="space-y-4 p-5">
                @csrf
                <div class="grid grid-cols-1 gap-4 {{ $marca ? 'sm:grid-cols-2' : 'sm:grid-cols-3' }}">
                    <div>
                        <span class="etiqueta">Estado</span>
                        <div class="grid grid-cols-3 gap-1 rounded-lg bg-carbon-100 p-1">
                            @foreach (['pendiente' => 'Pendiente', 'en_progreso' => 'Trabajando', 'en_espera' => 'En espera'] as $k => $v)
                                <label class="cursor-pointer rounded-md px-1 py-1.5 text-center text-xs font-semibold whitespace-nowrap transition"
                                       :class="estado === '{{ $k }}' ? 'bg-white text-carbon-900 shadow-sm' : 'text-carbon-500 hover:text-carbon-800'">
                                    <input type="radio" name="estado" value="{{ $k }}" x-model="estado" class="sr-only">{{ $v }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="etiqueta flex justify-between">Avance <span class="font-display font-extrabold text-marca-700" x-text="progreso + '%'"></span></label>
                        <input type="range" name="progreso" min="0" max="95" step="5" x-model="progreso" class="mt-2.5 w-full accent-marca-600">
                    </div>
                    @unless ($marca)
                    <div>
                        <label for="horas" class="etiqueta">Horas trabajadas hoy</label>
                        <input id="horas" name="horas" type="number" step="0.25" min="0" max="24" class="campo" placeholder="ej. 2.5">
                    </div>
                    @endunless
                </div>
                <div x-show="estado === 'en_espera'" x-collapse>
                    <label for="motivo_espera" class="etiqueta">¿Qué se está esperando?</label>
                    <input id="motivo_espera" name="motivo_espera" value="{{ $ot->motivo_espera }}" class="campo" placeholder="Repuesto, cotización, proveedor, paro de producción…">
                </div>
                <div>
                    <label for="texto" class="etiqueta">Nota del trabajo</label>
                    <textarea id="texto" name="texto" rows="2" class="campo" placeholder="Qué hiciste, qué encontraste, qué falta…"></textarea>
                </div>
                <div class="flex justify-end"><button class="btn-oscuro"><x-icono n="enviar" clase="size-4" /> Guardar avance</button></div>
            </form>
        </section>
        @endif

        {{-- Descripción y checklist --}}
        @if ($ot->descripcion || $ot->checklist)
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Trabajo a realizar</h3></div>
            <div class="space-y-4 p-5">
                @if ($ot->descripcion)<p class="text-sm leading-relaxed whitespace-pre-line text-carbon-700">{{ $ot->descripcion }}</p>@endif
                @if ($ot->checklist)
                    @php $hechos = collect($ot->checklist)->where('hecho', true)->count(); @endphp
                    <div x-data="{ hechos: {{ $hechos }} }">
                        <p class="mb-2 text-xs font-semibold text-carbon-500 uppercase">Lista de verificación · <span x-text="hechos"></span>/{{ count($ot->checklist) }}</p>
                        <ul class="space-y-1">
                            @foreach ($ot->checklist as $i => $item)
                                <li x-data="{ hecho: @js((bool) $item['hecho']) }">
                                    <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition {{ $permisos['ejecutar'] && $ot->estaAbierta() ? 'cursor-pointer hover:bg-carbon-50' : '' }}">
                                        <input type="checkbox" class="check" x-model="hecho" @disabled(! ($permisos['ejecutar'] && $ot->estaAbierta()))
                                               @change="hechos += hecho ? 1 : -1; api('{{ route('ot.checklist', $ot) }}', { method: 'POST', body: { indice: {{ $i }}, hecho } }).catch(e => { hecho = !hecho; hechos += hecho ? 1 : -1; avisar(e.message, 'error') })">
                                        <span class="text-sm" :class="hecho ? 'text-carbon-400 line-through' : 'text-carbon-800'">{{ $item['texto'] }}</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
        @endif

        @if ($ot->trabajo_realizado)
        <section class="tarjeta border-l-4 border-emerald-500">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Trabajo realizado</h3><span class="text-xs text-carbon-500">{{ $ot->completada_at?->format('d/m/Y H:i') }}</span></div>
            <p class="p-5 text-sm leading-relaxed whitespace-pre-line text-carbon-700">{{ $ot->trabajo_realizado }}</p>
        </section>
        @endif

        {{-- Seguimiento --}}
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Seguimiento</h3>
                <span class="text-xs text-carbon-500">{{ $ot->seguimientos->count() }} registros</span>
            </div>
            <form method="POST" action="{{ route('ot.comentario', $ot) }}" class="flex gap-3 border-b border-carbon-100 p-5">
                @csrf
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-carbon-900 text-xs font-bold text-white">{{ auth()->user()->iniciales() }}</span>
                <div class="flex-1">
                    <textarea name="texto" rows="2" required class="campo" placeholder="Escribe una pregunta o comentario para el equipo…"></textarea>
                    <div class="mt-2 flex justify-end"><button class="btn-secundario btn-sm"><x-icono n="mensaje" clase="size-4" /> Comentar</button></div>
                </div>
            </form>
            <ol class="px-5 py-4">
                @foreach ($ot->seguimientos as $s)
                    @php
                        [$icono, $color] = [
                            'comentario' => ['mensaje', 'bg-sky-50 text-sky-600 ring-sky-100'],
                            'avance' => ['tendencia', 'bg-marca-50 text-marca-600 ring-marca-100'],
                            'estado' => ['refrescar', 'bg-amber-50 text-amber-600 ring-amber-100'],
                            'asignacion' => ['usuario', 'bg-violet-50 text-violet-600 ring-violet-100'],
                            'sistema' => ['engrane', 'bg-carbon-100 text-carbon-500 ring-carbon-100'],
                        ][$s->tipo] ?? ['engrane', 'bg-carbon-100 text-carbon-500 ring-carbon-100'];
                        if ($s->estado === 'completada') { [$icono, $color] = ['check-circulo', 'bg-emerald-50 text-emerald-600 ring-emerald-100']; }
                    @endphp
                    <li class="relative flex gap-3 pb-5 last:pb-0">
                        @unless ($loop->last)<span class="absolute top-9 bottom-0 left-[17px] w-px bg-carbon-200"></span>@endunless
                        <span class="relative grid size-9 shrink-0 place-items-center rounded-full ring-4 {{ $color }}"><x-icono :n="$icono" clase="size-4" /></span>
                        <div class="min-w-0 flex-1 pt-1">
                            <p class="text-sm">
                                <span class="font-semibold text-carbon-900">{{ $s->user->name }}</span>
                                <span class="text-carbon-500">
                                    @switch($s->tipo)
                                        @case('comentario') comentó @break
                                        @case('estado') cambió el estado a <b class="text-carbon-700">{{ \App\Models\OrdenTrabajo::ESTADOS[$s->estado] ?? $s->estado }}</b> @break
                                        @case('avance') registró avance @break
                                        @case('asignacion') actualizó la asignación @break
                                        @default registró
                                    @endswitch
                                </span>
                                <span class="text-xs text-carbon-400" title="{{ $s->created_at->format('d/m/Y H:i') }}">· {{ $s->created_at->diffForHumans() }}</span>
                            </p>
                            @if ($s->progreso !== null || $s->horas)
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @if ($s->progreso !== null && $s->tipo !== 'sistema')<span class="insignia-gris">{{ $s->progreso }}%</span>@endif
                                    @if ($s->horas)<span class="insignia-gris"><x-icono n="reloj" clase="size-3" />@num($s->horas) h</span>@endif
                                </div>
                            @endif
                            @if ($s->texto)
                                <p class="mt-1.5 text-sm whitespace-pre-line {{ $s->tipo === 'comentario' ? 'rounded-lg rounded-tl-none bg-carbon-50 px-3 py-2 text-carbon-800' : 'text-carbon-700' }}">{{ $s->texto }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Evidencias --}}
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Fotos y evidencias</h3></div>
            <div class="p-5">
                <x-archivos :modelo="$ot" tipo="ot" :puede-subir="$permisos['ejecutar'] || $ot->solicitante_id === auth()->id()" :categorias="['evidencia' => 'Evidencia', 'foto' => 'Foto', 'documento' => 'Documento']" />
            </div>
        </section>
    </div>

    {{-- Columna derecha --}}
    <div class="space-y-6">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Detalles</h3></div>
            <dl class="divide-y divide-carbon-50 text-sm">
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <dt class="text-carbon-500">Responsable</dt>
                    <dd class="flex items-center gap-2 font-semibold">
                        @if ($ot->responsable)
                            <span class="grid size-7 place-items-center rounded-full bg-marca-600 text-[10px] font-bold text-white">{{ $ot->responsable->iniciales() }}</span><span class="truncate">{{ $ot->responsable->name }}</span>
                        @else
                            <span class="insignia-ambar">Sin asignar</span>
                        @endif
                    </dd>
                </div>
                @if ($ot->ayudantes->isNotEmpty())
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Apoyo</dt><dd class="text-right font-medium">{{ $ot->ayudantes->pluck('name')->join(', ') }}</dd></div>
                @endif
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Especialidad</dt><dd class="font-medium">{{ $ot->especialidad?->nombre ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Solicitó</dt><dd class="font-medium">{{ $ot->solicitante?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Inicio</dt><dd class="font-medium">@fecha($ot->fecha_inicio)</dd></div>
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Vencimiento</dt><dd class="font-medium">@fecha($ot->fecha_vencimiento)</dd></div>
                @if ($ot->completada_at)
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Completada</dt><dd class="font-medium">{{ $ot->completada_at->format('d/m/Y H:i') }}</dd></div>
                @endif
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Horas trabajadas</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Formato::duracion((float) $ot->horas_trabajo + $ot->tramos->whereNull('fin_at')->sum(fn ($t) => $t->horas())) }}</dd></div>
                @if ($ot->detuvo_maquina)
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Paro de máquina</dt><dd class="font-semibold text-marca-700">@num($ot->horas_paro ?? 0) h</dd></div>
                @endif
                @if ($ot->plan)
                <div class="flex justify-between gap-3 px-5 py-3"><dt class="text-carbon-500">Plan preventivo</dt><dd class="text-right font-medium">{{ $ot->plan->frecuenciaTexto() }}</dd></div>
                @endif
            </dl>
        </section>

        {{-- Repuestos --}}
        <section class="tarjeta">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Repuestos usados</h3>
                @if ($repuestos->isNotEmpty())
                    <button class="btn-secundario btn-sm" @click="$dispatch('abrir-modal', 'repuestos')"><x-icono n="mas" clase="size-4" /> Agregar</button>
                @endif
            </div>
            @php $lineas = $ot->movimientos->where('estado', 'confirmado')->flatMap->lineas; @endphp
            @forelse ($lineas as $l)
                <div class="flex items-center justify-between gap-3 border-b border-carbon-50 px-5 py-2.5 text-sm last:border-0">
                    <span class="min-w-0 truncate">{{ $l->producto->nombre }}</span>
                    <span class="shrink-0 font-semibold tabular-nums">@num($l->cantidad) {{ $l->producto->unidad->abreviatura }}</span>
                </div>
            @empty
                <p class="px-5 py-5 text-center text-sm text-carbon-500">Sin repuestos registrados.</p>
            @endforelse
        </section>
    </div>
</div>

{{-- Modal: completar --}}
@if ($permisos['ejecutar'] && $ot->estaAbierta())
<x-modal nombre="completar" titulo="Completar orden de trabajo" ancho="max-w-2xl">
    <form method="POST" action="{{ route('ot.completar', $ot) }}" class="space-y-4" x-data="{ paro: false, externo: false }">
        @csrf
        @if ($ot->maquina)
            <p class="flex items-start gap-2 rounded-lg bg-sky-50 px-3 py-2.5 text-sm text-sky-900 ring-1 ring-sky-200">
                <x-icono n="libro" clase="mt-0.5 size-4 shrink-0" /> Al completar, el trabajo se registra automáticamente en la bitácora de <b>{{ $ot->maquina->etiqueta() }}</b>.
            </p>
        @endif
        <div>
            <label for="trabajo_realizado" class="etiqueta">Trabajo realizado <span class="text-marca-600">*</span></label>
            <textarea id="trabajo_realizado" name="trabajo_realizado" rows="4" required class="campo" placeholder="Describe lo que se hizo: piezas cambiadas, ajustes, pruebas…"></textarea>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div><label class="etiqueta" for="componente">Componente</label><input id="componente" name="componente" class="campo" placeholder="ej. Motor, Bomba"></div>
            @if ($marca)
            <div><span class="etiqueta">Tiempo laboral</span><p class="campo bg-carbon-50 font-semibold tabular-nums">{{ \App\Support\Formato::duracion($tiempo) }}</p></div>
            @else
            <div><label class="etiqueta" for="horas_c">Horas de esta jornada</label><input id="horas_c" name="horas" type="number" step="0.25" min="0" class="campo"></div>
            @endif
            <div><label class="etiqueta" for="horometro">Horómetro actual</label><input id="horometro" name="horometro" type="number" step="0.1" min="0" class="campo" placeholder="{{ $ot->maquina?->horometro }}"></div>
        </div>
        <div class="space-y-3 rounded-xl bg-carbon-50 p-4">
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="detuvo_maquina" value="1" x-model="paro" class="check"> La máquina estuvo detenida por este trabajo</label>
            <div x-show="paro" x-collapse><label class="etiqueta" for="horas_paro">Horas de paro</label><input id="horas_paro" name="horas_paro" type="number" step="0.25" min="0" class="campo max-w-40"></div>
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" x-model="externo" class="check"> Participó un proveedor externo</label>
            <div x-show="externo" x-collapse class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <select name="proveedor_id" class="campo sm:col-span-2" :disabled="!externo">
                    <option value="">Proveedor…</option>
                    @foreach ($proveedores as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
                </select>
                <input name="costo" type="number" step="0.01" min="0" class="campo" placeholder="Costo Q" :disabled="!externo">
                <label class="flex items-center gap-2 text-sm sm:col-span-3"><input type="checkbox" name="garantia" value="1" class="check"> Es reclamo por garantía</label>
            </div>
        </div>
        <div><label class="etiqueta" for="comentarios">Comentarios</label><textarea id="comentarios" name="comentarios" rows="2" class="campo"></textarea></div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secundario" @click="$dispatch('cerrar-modal')">Cancelar</button>
            <button class="btn-exito"><x-icono n="check-circulo" clase="size-4" /> Completar y registrar</button>
        </div>
    </form>
</x-modal>
@endif

@if ($permisos['cancelar'])
<x-modal nombre="cancelar" titulo="Cancelar orden">
    <form method="POST" action="{{ route('ot.cancelar', $ot) }}" class="space-y-4">
        @csrf
        <div><label for="motivo" class="etiqueta">Motivo <span class="text-marca-600">*</span></label><textarea id="motivo" name="motivo" rows="3" required class="campo" placeholder="Por qué ya no se hará este trabajo"></textarea></div>
        <div class="flex justify-end gap-2">
            <button type="button" class="btn-secundario" @click="$dispatch('cerrar-modal')">Volver</button>
            <button class="btn bg-marca-600 text-white hover:bg-marca-700">Cancelar orden</button>
        </div>
    </form>
</x-modal>
@endif

@if (request('completar') && $permisos['ejecutar'] && $ot->estaAbierta())
    <div x-data x-init="setTimeout(() => $dispatch('abrir-modal', 'completar'), 150)"></div>
@endif

@if ($repuestos->isNotEmpty())
<x-modal nombre="repuestos" titulo="Repuestos usados" ancho="max-w-2xl">
    <form method="POST" action="{{ route('ot.repuestos', $ot) }}" class="space-y-4" x-data="filas([], { producto_id: '', cantidad: 1 })">
        @csrf
        <p class="text-sm text-carbon-600">Se descuentan de la bodega de repuestos como <b>consumo en mantenimiento</b> ligado a esta orden.</p>
        <template x-for="(f, i) in filas" :key="i">
            <div class="flex items-center gap-2">
                <select :name="`lineas[${i}][producto_id]`" x-model="f.producto_id" required class="campo flex-1">
                    <option value="">Repuesto…</option>
                    @foreach ($repuestos as $r)<option value="{{ $r->id }}">{{ $r->codigo }} · {{ $r->nombre }} ({{ \App\Support\Formato::numero($r->existencia) }} {{ $r->unidad->abreviatura }})</option>@endforeach
                </select>
                <input :name="`lineas[${i}][cantidad]`" x-model="f.cantidad" type="number" step="any" min="0" required class="campo w-24">
                <button type="button" class="rounded-md p-2 text-carbon-400 hover:bg-carbon-100 hover:text-marca-700" @click="quitar(i)"><x-icono n="x" clase="size-4" /></button>
            </div>
        </template>
        <button type="button" class="text-sm font-semibold text-marca-700 hover:underline" @click="agregar()">+ Otro repuesto</button>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secundario" @click="$dispatch('cerrar-modal')">Cancelar</button>
            <button class="btn-oscuro"><x-icono n="check" clase="size-4" /> Descontar de la bodega</button>
        </div>
    </form>
</x-modal>
@endif
</x-layouts.app>
