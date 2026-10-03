<x-layouts.app :titulo="$p->folio">
<x-slot:migas><a href="{{ route('pedidos.index') }}" class="hover:text-carbon-800">Pedidos</a><x-icono n="derecha" clase="size-3" />{{ $p->folio }}</x-slot:migas>
@php
    $pasos = $p->pasos();
    $iActual = array_search($p->estado, $pasos, true);
    $nombresPaso = ['nuevo' => 'Ingresado', 'preparando' => 'Armando', 'listo' => 'Listo', 'en_ruta' => 'En ruta', 'entregado' => 'Entregado'];
    $fechaPaso = ['nuevo' => $p->created_at, 'preparando' => $p->preparando_at, 'listo' => $p->listo_at, 'en_ruta' => $p->en_ruta_at, 'entregado' => $p->entregado_at];
    [$armadas, $total] = $p->progresoPreparacion();
    $dias = today()->diffInDays($p->fecha_entrega, false);
    $lineasJs = $p->lineas->map(fn ($l) => [
        'id' => $l->id, 'nombre' => $l->producto->nombre.($l->producto->color ? ' · '.$l->producto->color : ''), 'codigo' => $l->producto->codigo,
        'color' => $l->producto->color, 'pedido' => $l->descripcionCantidad(), 'cantidad_base' => (float) $l->cantidad_base, 'unidad' => $l->producto->unidad->abreviatura,
        'preparada' => $l->preparada, 'cantidad_preparada' => $l->cantidad_preparada !== null ? (float) $l->cantidad_preparada : null,
        'stock' => (float) $l->producto->existencias->firstWhere('bodega_id', $p->bodega_id)?->cantidad, 'notas' => $l->notas, 'editando' => false, 'tmp' => null,
    ])->values();
@endphp
<script>document.body.dataset.estadoPedido = @js($p->estado);</script>

{{-- Encabezado --}}
<div class="tarjeta overflow-hidden">
    <div class="p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm font-bold text-carbon-400">{{ $p->folio }}</span>
            <x-pedido.estado :estado="$p->estado" />
            @if ($p->prioridad === 'urgente')<span class="insignia-roja"><x-icono n="rayo" clase="size-3" /> Urgente</span>@endif
            @if ($p->atrasado())<span class="insignia-roja"><x-icono n="alerta" clase="size-3" /> Atrasado</span>@endif
        </div>
        <h2 class="mt-2 font-display text-xl leading-tight font-extrabold tracking-tight sm:text-2xl">
            <a href="{{ route('clientes.show', $p->cliente) }}" class="hover:text-marca-700">{{ $p->cliente->nombre }}</a>
        </h2>
        <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-carbon-600">
            <span class="inline-flex items-center gap-1 font-semibold {{ $p->atrasado() ? 'text-marca-700' : 'text-carbon-800' }}"><x-icono n="calendario" clase="size-4" />
                {{ $dias == 0 ? 'Hoy' : ($dias == 1 ? 'Mañana' : ucfirst($p->fecha_entrega->translatedFormat('l j \d\e F'))) }}{{ $p->jornada ? ' · '.mb_strtolower(\App\Models\Pedido::JORNADAS[$p->jornada]) : '' }}</span>
            <span class="text-carbon-300">·</span> {{ \App\Models\Pedido::TIPOS_ENTREGA[$p->tipo_entrega] }}
            @if ($p->viaje && $p->estado === 'en_ruta')
                <a href="{{ route('viajes.show', $p->viaje) }}" class="insignia-ambar hover:underline"><x-icono n="camion" clase="size-3" /> {{ $p->viaje->folio }} · parada {{ $p->orden_parada }}</a>
            @endif
        </p>
    </div>

    {{-- Recorrido del pedido --}}
    @if ($p->estado !== 'cancelado')
    <ol class="flex border-t border-carbon-100 bg-carbon-50/60 px-2 py-3 sm:px-4">
        @foreach ($pasos as $i => $paso)
            @php $hecho = $i < $iActual; $actual = $i === $iActual; @endphp
            <li class="relative flex flex-1 flex-col items-center text-center">
                @if ($i > 0)<span class="absolute top-3.5 right-1/2 left-[-50%] h-0.5 {{ $i <= $iActual ? 'bg-emerald-500' : 'bg-carbon-200' }}"></span>@endif
                <span class="relative grid size-7 place-items-center rounded-full text-xs font-bold ring-4 ring-carbon-50 {{ $hecho || ($actual && $p->estado === 'entregado') ? 'bg-emerald-500 text-white' : ($actual ? 'bg-marca-600 text-white' : 'bg-white text-carbon-400 ring-1 ring-carbon-200') }}">
                    @if ($hecho || ($actual && $p->estado === 'entregado'))<x-icono n="check" clase="size-4" />@else{{ $i + 1 }}@endif
                    @if ($actual && $p->estado !== 'entregado')<span class="absolute inset-0 animate-ping rounded-full bg-marca-500 opacity-30 [animation-duration:2s]"></span>@endif
                </span>
                <span class="mt-1 text-[11px] font-semibold {{ $actual ? 'text-carbon-900' : 'text-carbon-500' }}">{{ $nombresPaso[$paso] }}</span>
                @if ($fechaPaso[$paso] && $i <= $iActual)<span class="hidden text-[10px] text-carbon-400 sm:block">{{ $fechaPaso[$paso]->format('d/m H:i') }}</span>@endif
            </li>
        @endforeach
    </ol>
    @else
        <p class="flex items-center gap-2 border-t border-carbon-100 bg-carbon-50 px-5 py-3 text-sm text-carbon-700"><x-icono n="x" clase="size-4" /> Cancelado el {{ $p->cancelado_at?->format('d/m/Y H:i') }}: {{ $p->motivo_cancelacion }}</p>
    @endif
</div>

<div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
    <div class="space-y-5 xl:col-span-2">

        {{-- Siguiente paso: una sola acción clara según el estado --}}
        @if (($permisos['preparar'] || $permisos['entregar']) && $p->estaActivo())
            @if (! $permisos['preparar'] && $p->estado !== 'en_ruta')
                {{-- El piloto sin permisos de bodega solo actúa al entregar --}}
            @elseif ($p->estado === 'nuevo')
                <form method="POST" action="{{ route('pedidos.tomar', $p) }}" class="tarjeta flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:p-5">
                    @csrf
                    <div class="min-w-0 flex-1">
                        <p class="font-display font-bold">¿Lo armas tú?</p>
                        <p class="text-sm text-carbon-500">Queda a tu nombre y {{ $p->vendedor->name }} sabrá que ya se está preparando.</p>
                    </div>
                    <button class="btn-primario h-12 px-5 text-base"><x-icono n="cajas" clase="size-5" /> Empezar a armar</button>
                </form>
            @elseif ($p->estado === 'listo' && $p->tipo_entrega !== 'recoge')
                <form method="POST" action="{{ route('pedidos.despachar', $p) }}" class="tarjeta" data-confirmar="Se descontará del inventario lo que se armó." data-titulo="¿Despachar {{ $p->folio }}?" data-boton="Sí, despachar">
                    @csrf
                    <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Despachar</h3><span class="text-xs text-carbon-500">Al despachar sale del inventario</span></div>
                    <div class="space-y-4 p-4 sm:p-5">
                        @if ($p->tipo_entrega === 'ruta')
                            <a href="{{ route('viajes.create', ['pedidos' => [$p->id]]) }}" class="flex items-center gap-2 rounded-lg bg-carbon-50 px-3 py-2 text-sm ring-1 ring-carbon-200 hover:bg-carbon-100">
                                <x-icono n="camion" clase="size-4 text-marca-600" /><span class="flex-1"><b>¿Va con otros pedidos?</b> Arma un viaje con varias paradas.</span><x-icono n="derecha" clase="size-4 text-carbon-400" />
                            </a>
                            {{-- Flota propia: los vehículos registrados en Máquinas (área Vehículos) --}}
                            <fieldset>
                                <legend class="etiqueta">Vehículo</legend>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @forelse ($vehiculos as $v)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl p-3 ring-1 ring-carbon-200 transition has-checked:bg-marca-50 has-checked:ring-2 has-checked:ring-marca-500">
                                            <input type="radio" name="vehiculo_id" value="{{ $v->id }}" class="text-marca-600 focus:ring-marca-500" required @checked($vehiculos->count() === 1 || old('vehiculo_id') == $v->id)>
                                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-carbon-100 text-carbon-600"><x-icono n="camion" clase="size-5" /></span>
                                            <span class="min-w-0 flex-1 leading-tight">
                                                <span class="block text-sm font-semibold">{{ $v->codigo }} · {{ $v->nombre }}</span>
                                                <span class="block text-xs text-carbon-500">{{ $v->marca }}</span>
                                            </span>
                                            @if ($v->estado !== 'operativa')<x-maquina.estado :estado="$v->estado" />@endif
                                        </label>
                                    @empty
                                        <p class="text-sm text-carbon-500 sm:col-span-2">No hay vehículos registrados. Agrégalos en Máquinas con el área <b>Vehículos</b>.</p>
                                    @endforelse
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend class="etiqueta">Piloto</legend>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($pilotos as $pi)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-full py-1.5 pr-4 pl-1.5 text-sm font-semibold ring-1 ring-carbon-200 transition has-checked:bg-carbon-900 has-checked:text-white has-checked:ring-carbon-900">
                                            <input type="radio" name="piloto_id" value="{{ $pi->id }}" class="sr-only" required @checked($pilotos->count() === 1 || old('piloto_id') == $pi->id)>
                                            <span class="grid size-7 place-items-center rounded-full bg-carbon-100 text-[10px] font-bold text-carbon-700">{{ $pi->iniciales() }}</span>{{ $pi->name }}
                                        </label>
                                    @empty
                                        <p class="text-sm text-carbon-500">No hay pilotos. @can('usuarios.gestionar')<a href="{{ route('usuarios.index') }}" class="font-semibold text-marca-700 underline">Marca a un usuario como piloto</a>.@else Pide al administrador que marque a los pilotos en Usuarios.@endcan</p>
                                    @endforelse
                                </div>
                                <p class="ayuda">Le llega el aviso con la dirección y puede confirmar la entrega desde su teléfono.</p>
                            </fieldset>
                        @else
                            {{-- Transporte / encomienda: empresa externa --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div><label class="etiqueta" for="empresa">Empresa de transporte *</label><input id="empresa" name="empresa" class="campo" list="transportes" required value="{{ old('empresa') }}"></div>
                                <datalist id="transportes"><option value="Cargo Expreso"><option value="Guatex"><option value="Forza Delivery"><option value="Transportes del cliente"></datalist>
                                <div><label class="etiqueta" for="guia">No. de guía</label><input id="guia" name="guia" class="campo" value="{{ old('guia') }}"></div>
                            </div>
                        @endif
                        <button class="btn-primario h-12 w-full text-base"><x-icono n="camion" clase="size-5" /> Salió a ruta</button>
                    </div>
                </form>
            @elseif (($p->estado === 'en_ruta') || ($p->estado === 'listo' && $p->tipo_entrega === 'recoge'))
                <form method="POST" action="{{ route('pedidos.entregar', $p) }}" class="tarjeta"
                      @if ($p->tipo_entrega === 'recoge') data-confirmar="Se descontará del inventario lo que se armó." data-titulo="¿Entregar {{ $p->folio }}?" data-boton="Sí, entregado" @endif>
                    @csrf
                    <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">{{ $p->tipo_entrega === 'recoge' ? 'Entregar al cliente' : 'Confirmar entrega' }}</h3></div>
                    <div class="grid grid-cols-2 gap-3 p-4 sm:p-5">
                        <div class="col-span-2 sm:col-span-1"><label class="etiqueta" for="recibido_por">¿Quién recibió? *</label><input id="recibido_por" name="recibido_por" class="campo" required value="{{ old('recibido_por', $p->contacto_nombre) }}"></div>
                        <div class="col-span-2 sm:col-span-1"><label class="etiqueta" for="nota">Nota</label><input id="nota" name="nota" class="campo" placeholder="Opcional"></div>
                        <button class="btn-exito col-span-2 h-12 text-base"><x-icono n="check-circulo" clase="size-5" /> Entregado</button>
                    </div>
                </form>
            @endif
        @endif

        {{-- Productos: en preparación se marcan al tocarlos --}}
        <section class="tarjeta" x-data="armarPedido({ url: @js(route('pedidos.linea', [$p, '__LINEA__'])), lineas: @js($lineasJs) })">
            <div class="tarjeta-cabeza">
                <h3 class="tarjeta-titulo">Productos</h3>
                @if (in_array($p->estado, ['nuevo', 'preparando']))
                    <span class="flex items-center gap-2 text-xs font-semibold text-carbon-600">
                        <span class="h-1.5 w-20 overflow-hidden rounded-full bg-carbon-100"><span class="block h-full rounded-full bg-sky-500 transition-all duration-300" :style="`width:${lineas.length ? armadas / lineas.length * 100 : 0}%`"></span></span>
                        <span x-text="`${armadas}/${lineas.length} armados`"></span>
                    </span>
                @else
                    <span class="text-xs text-carbon-500">{{ $total }} {{ $total === 1 ? 'producto' : 'productos' }}</span>
                @endif
            </div>
            @php $puedeMarcar = $permisos['preparar'] && in_array($p->estado, ['nuevo', 'preparando']); @endphp
            <ul class="divide-y divide-carbon-100">
                <template x-for="l in lineas" :key="l.id">
                    <li class="flex items-start gap-3 px-4 py-3 transition-colors" :class="l.preparada && 'bg-emerald-50/50'">
                        @if ($puedeMarcar)
                            <button type="button" @click="marcar(l, !l.preparada)" :disabled="guardando === l.id" :aria-pressed="l.preparada.toString()"
                                    class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg ring-2 transition active:scale-90"
                                    :class="l.preparada ? 'bg-emerald-500 text-white ring-emerald-500' : 'bg-white text-transparent ring-carbon-300 hover:ring-emerald-400'"
                                    :aria-label="l.preparada ? 'Desmarcar' : 'Marcar como armado'">
                                <x-icono n="check" clase="size-5" />
                            </button>
                        @else
                            <span class="mt-1.5 size-2.5 shrink-0 rounded-full ring-1 ring-carbon-300" :style="`background:${colorMuestra(l.color)}`"></span>
                        @endif
                        <div class="min-w-0 flex-1" @if ($puedeMarcar) @click="!l.editando && marcar(l, !l.preparada)" @endif>
                            <p class="text-sm font-semibold leading-snug text-carbon-900" :class="l.preparada && '{{ $puedeMarcar ? 'line-through decoration-emerald-600/40' : '' }}'" x-text="l.nombre"></p>
                            <p class="text-xs text-carbon-500">
                                <span class="font-mono" x-text="l.codigo"></span>
                                @if (in_array($p->estado, ['nuevo', 'preparando']))
                                    · <span :class="l.stock < l.cantidad_base ? 'font-semibold text-amber-700' : ''" x-text="`en bodega ${fmt(l.stock)} ${l.unidad}`"></span>
                                @endif
                            </p>
                            <p x-show="l.notas" class="mt-0.5 text-xs text-carbon-600 italic" x-text="l.notas"></p>
                            <p x-show="l.preparada && l.cantidad_preparada !== null && l.cantidad_preparada < l.cantidad_base" class="mt-1 text-xs font-semibold text-amber-700"
                               x-text="`Se armaron ${fmt(l.cantidad_preparada)} de ${fmt(l.cantidad_base)} ${l.unidad}`"></p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-bold tabular-nums" x-text="l.pedido"></p>
                            <p class="text-xs text-carbon-500 tabular-nums" x-text="`${fmt(l.cantidad_base)} ${l.unidad}`"></p>
                            @if ($puedeMarcar)
                                <button type="button" x-show="!l.editando" @click.stop="editar(l)" class="mt-1 text-xs font-semibold text-carbon-500 underline decoration-dotted hover:text-marca-700">¿No alcanzó?</button>
                                <div x-show="l.editando" x-cloak class="mt-1 flex items-center gap-1" @click.stop>
                                    <input :id="'parcial-' + l.id" x-model="l.tmp" type="number" inputmode="decimal" min="0" step="any" class="campo w-20 px-2 py-1 text-right text-sm" @keydown.enter.prevent="guardarParcial(l)" @keydown.escape="l.editando = false">
                                    <button type="button" class="btn-oscuro btn-sm" @click="guardarParcial(l)">OK</button>
                                </div>
                            @endif
                        </div>
                    </li>
                </template>
            </ul>
            @if ($permisos['preparar'] && $p->estado === 'preparando')
                <form method="POST" action="{{ route('pedidos.listo', $p) }}" class="border-t border-carbon-100 p-4">
                    @csrf
                    <button class="btn-exito h-12 w-full text-base disabled:opacity-40" :disabled="!completo">
                        <x-icono n="check-circulo" clase="size-5" /> <span x-text="completo ? 'Pedido listo' : `Faltan ${lineas.length - armadas} por armar`"></span>
                    </button>
                </form>
            @endif
        </section>

        @if ($p->notas)
            <section class="rounded-xl bg-amber-50 p-4 text-sm text-amber-950 ring-1 ring-amber-200">
                <p class="mb-1 flex items-center gap-1.5 font-semibold"><x-icono n="mensaje" clase="size-4" /> Indicaciones</p>
                <p class="whitespace-pre-line">{{ $p->notas }}</p>
            </section>
        @endif

        {{-- Seguimiento --}}
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Seguimiento</h3></div>
            <form method="POST" action="{{ route('pedidos.comentar', $p) }}" class="flex items-start gap-2 border-b border-carbon-100 p-4">
                @csrf
                <textarea name="texto" rows="1" required class="campo min-h-[2.625rem] flex-1 resize-none" placeholder="Escribe un comentario…"
                          x-data @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
                <button class="btn-secundario btn-icono h-[2.625rem]" aria-label="Enviar comentario"><x-icono n="enviar" clase="size-4" /></button>
            </form>
            <ol class="space-y-4 p-4">
                @foreach ($p->seguimientos as $s)
                    <li class="flex gap-3">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full text-[10px] font-bold {{ $s->estado ? 'bg-carbon-900 text-white' : 'bg-carbon-100 text-carbon-700' }}">{{ $s->user->iniciales() }}</span>
                        <div class="min-w-0 flex-1 text-sm">
                            <p><b>{{ $s->user->name }}</b> <span class="text-xs text-carbon-400">· {{ $s->created_at->diffForHumans() }}</span></p>
                            @if ($s->estado)<x-pedido.estado :estado="$s->estado" class="mt-0.5" />@endif
                            @if ($s->texto)<p class="mt-0.5 whitespace-pre-line text-carbon-700">{{ $s->texto }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    {{-- Datos del pedido --}}
    <div class="space-y-5">
        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Entrega</h3></div>
            <dl class="divide-y divide-carbon-50 text-sm">
                @if ($p->tipo_entrega !== 'recoge' && $p->direccion_entrega)
                    <div class="px-4 py-3"><dt class="dato-etiqueta">Dirección</dt><dd class="mt-0.5 font-medium">{{ $p->direccion_entrega }}</dd>
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($p->direccion_entrega) }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-marca-700 hover:underline"><x-icono n="ubicacion" clase="size-3.5" /> Abrir en mapas</a></div>
                @endif
                @if ($p->contacto_nombre || $p->contacto_telefono)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1"><dt class="dato-etiqueta">Recibe</dt><dd class="mt-0.5 font-medium">{{ $p->contacto_nombre ?? '—' }}</dd></div>
                        @if ($p->contacto_telefono)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $p->contacto_telefono) }}" class="btn-secundario btn-sm"><x-icono n="telefono" clase="size-4" /> {{ $p->contacto_telefono }}</a>@endif
                    </div>
                @endif
                @foreach (['Sale de' => $p->bodega->nombre, 'Orden de compra' => $p->orden_compra, 'Pago' => \App\Models\Pedido::CONDICIONES_PAGO[$p->condicion_pago] ?? null,
                           ($p->vehiculo_id ? 'Vehículo' : 'Transporte') => $p->nombreVehiculo(), 'Piloto' => $p->piloto_id ? null : $p->piloto, 'No. de guía' => $p->tipo_entrega === 'transporte' ? $p->documento : null, 'Recibió' => $p->recibido_por] as $k => $v)
                    @if ($v)<div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-carbon-500">{{ $k }}</dt><dd class="text-right font-medium">{{ $v }}</dd></div>@endif
                @endforeach
                @if ($p->movimiento)
                    <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-carbon-500">Salida de inventario</dt><dd><a href="{{ route('movimientos.show', $p->movimiento) }}" class="font-mono font-semibold text-marca-700 hover:underline">{{ $p->movimiento->folio }}</a></dd></div>
                @endif
            </dl>
        </section>

        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Personas</h3></div>
            <dl class="divide-y divide-carbon-50 text-sm">
                @foreach (['Vendió' => $p->vendedor, 'Armó' => $p->preparador, 'Despachó' => $p->despachador, 'Piloto' => $p->pilotoAsignado] as $k => $u)
                    @if ($u)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5"><dt class="text-carbon-500">{{ $k }}</dt>
                            <dd class="flex items-center gap-2 font-medium"><span class="grid size-6 place-items-center rounded-full bg-carbon-100 text-[9px] font-bold">{{ $u->iniciales() }}</span>{{ $u->name }}</dd></div>
                    @endif
                @endforeach
            </dl>
        </section>

        @if ($p->estado !== 'nuevo')
            <section class="tarjeta p-4">
                <x-archivos :modelo="$p" tipo="pedido" titulo="Fotos" :puede-subir="$permisos['preparar'] || $permisos['entregar']" />
            </section>
        @endif

        @if ($permisos['editar'] || $permisos['cancelar'])
            <div class="flex flex-wrap gap-2">
                @if ($permisos['editar'])<a href="{{ route('pedidos.edit', $p) }}" class="btn-secundario flex-1"><x-icono n="lapiz" clase="size-4" /> Editar</a>@endif
                @if ($permisos['cancelar'])<button type="button" class="btn-peligro flex-1" @click="$dispatch('abrir-modal', 'cancelar')"><x-icono n="x" clase="size-4" /> Cancelar pedido</button>@endif
            </div>
        @endif
    </div>
</div>

@if ($permisos['cancelar'])
    <x-modal nombre="cancelar" titulo="Cancelar {{ $p->folio }}">
        <form method="POST" action="{{ route('pedidos.cancelar', $p) }}" class="space-y-4">
            @csrf
            <p class="text-sm text-carbon-600">Se avisará a {{ $p->preparador ? $p->preparador->name.' (lo estaba armando)' : 'bodega' }}{{ $p->vendedor_id !== auth()->id() ? ' y a '.$p->vendedor->name : '' }}. No se modifica el inventario.</p>
            <x-campo nombre="motivo" etiqueta="Motivo" requerido placeholder="Ej. el cliente canceló, se duplicó…" />
            <div class="flex justify-end gap-2"><button type="button" class="btn-secundario" @click="abierto = false">Volver</button><button class="btn bg-marca-600 text-white hover:bg-marca-700">Cancelar pedido</button></div>
        </form>
    </x-modal>
@endif
</x-layouts.app>
