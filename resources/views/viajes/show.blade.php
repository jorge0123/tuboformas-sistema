<x-layouts.app :titulo="$v->folio">
<x-slot:migas><a href="{{ route('viajes.index') }}" class="hover:text-carbon-800">Viajes</a><x-icono n="derecha" clase="size-3" />{{ $v->folio }}</x-slot:migas>
@php
    $total = $v->pedidos->count();
    $entregados = $v->pedidos->where('estado', 'entregado')->count();
    $pendientes = $v->pedidos->where('estado', 'en_ruta');
    // Ruta completa en Google Maps: sale desde donde está el piloto y pasa por las paradas pendientes en orden.
    $dirs = $pendientes->map(fn ($p) => $p->direccion_entrega)->filter()->values();
    $mapa = $dirs->isNotEmpty() ? 'https://www.google.com/maps/dir/?api=1&travelmode=driving&destination='.urlencode($dirs->last())
        .($dirs->count() > 1 ? '&waypoints='.urlencode($dirs->slice(0, -1)->join('|')) : '') : null;
    $siguiente = $pendientes->first();
@endphp

<div class="tarjeta overflow-hidden">
    <div class="flex items-start gap-4 p-4 sm:p-5">
        <span class="grid size-12 shrink-0 place-items-center rounded-xl {{ $v->estado === 'en_ruta' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' }}"><x-icono n="camion" clase="size-6" /></span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-sm font-bold text-carbon-400">{{ $v->folio }}</span>
                <span class="{{ $v->estado === 'en_ruta' ? 'insignia-ambar' : 'insignia-verde' }}">{{ \App\Models\Viaje::ESTADOS[$v->estado] }}</span>
            </div>
            <h2 class="mt-1 font-display text-xl font-extrabold">{{ $v->vehiculo->codigo }} · {{ $v->vehiculo->nombre }} <span class="text-base font-semibold text-carbon-500">{{ $v->vehiculo->marca }}</span></h2>
            <p class="text-sm text-carbon-600">{{ $v->piloto->name }} · salió {{ $v->salida_at->translatedFormat('D d M, H:i') }}{{ $v->regreso_at ? ' · terminó '.$v->regreso_at->format('H:i') : '' }}</p>
        </div>
    </div>
    <div class="border-t border-carbon-100 px-4 py-3 sm:px-5">
        <div class="mb-1.5 flex justify-between text-sm"><span class="font-medium text-carbon-600">Entregas</span><span class="font-display font-extrabold tabular-nums">{{ $entregados }} de {{ $total }}</span></div>
        <div class="flex h-2 gap-1">
            @foreach ($v->pedidos as $p)
                <span class="flex-1 rounded-full {{ $p->estado === 'entregado' ? 'bg-emerald-500' : ($p->estado === 'en_ruta' ? 'bg-carbon-200' : 'bg-marca-400') }}"></span>
            @endforeach
        </div>
    </div>
    @if ($mapa && $v->estado === 'en_ruta')
        <a href="{{ $mapa }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 border-t border-carbon-100 bg-carbon-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-carbon-800">
            <x-icono n="ubicacion" clase="size-4" /> Abrir ruta en Google Maps ({{ $dirs->count() }} {{ $dirs->count() === 1 ? 'parada' : 'paradas' }})
        </a>
    @endif
    @if ($v->notas)<p class="flex gap-2 border-t border-carbon-100 bg-amber-50 px-4 py-3 text-sm text-amber-950"><x-icono n="mensaje" clase="size-4 shrink-0" /> {{ $v->notas }}</p>@endif
</div>

{{-- Paradas --}}
<ol class="mt-5 space-y-3">
    @foreach ($v->pedidos as $p)
        @php $esSiguiente = $siguiente && $siguiente->id === $p->id; @endphp
        <li class="tarjeta overflow-hidden {{ $esSiguiente ? 'ring-2 ring-marca-500' : '' }} {{ $p->estado !== 'en_ruta' ? 'opacity-80' : '' }}" x-data="{ fallo: false }">
            <div class="flex items-start gap-3 p-4">
                <span class="grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold
                    {{ $p->estado === 'entregado' ? 'bg-emerald-500 text-white' : ($p->estado === 'en_ruta' ? ($esSiguiente ? 'bg-marca-600 text-white' : 'bg-carbon-900 text-white') : 'bg-marca-100 text-marca-700') }}">
                    @if ($p->estado === 'entregado')<x-icono n="check" clase="size-4" />@elseif ($p->estado !== 'en_ruta')<x-icono n="deshacer" clase="size-4" />@else{{ $p->orden_parada }}@endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-x-2 text-xs font-semibold text-carbon-500">
                        @if ($esSiguiente)<span class="font-bold text-marca-700 uppercase">Siguiente</span>@endif
                        <a href="{{ route('pedidos.show', $p) }}" class="font-mono hover:underline">{{ $p->folio }}</a>
                        @if ($p->estado === 'entregado')<span class="text-emerald-700">Entregado {{ $p->entregado_at?->format('H:i') }} · recibió {{ $p->recibido_por }}</span>
                        @elseif ($p->estado !== 'en_ruta')<span class="text-marca-700">No se entregó · regresa a bodega</span>@endif
                    </p>
                    <p class="font-display text-lg leading-tight font-bold">{{ $p->cliente->nombre }}</p>
                    @if ($p->direccion_entrega)<p class="mt-0.5 text-sm text-carbon-600">{{ $p->direccion_entrega }}</p>@endif
                    <p class="mt-1 text-xs text-carbon-500">{{ $p->lineas->map(fn ($l) => $l->descripcionCantidad().' '.$l->producto->nombre.($l->producto->color ? ' '.$l->producto->color : ''))->join(' · ') }}</p>
                    @if ($p->notas)<p class="mt-1 text-xs text-amber-800 italic">{{ $p->notas }}</p>@endif
                </div>
            </div>
            @if ($p->estado === 'en_ruta')
                <div class="grid grid-cols-2 gap-2 border-t border-carbon-100 px-4 py-3">
                    @if ($p->contacto_telefono)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $p->contacto_telefono) }}" class="btn-secundario"><x-icono n="telefono" clase="size-4" /> Llamar</a>
                    @endif
                    @if ($p->direccion_entrega)
                        <a href="https://www.google.com/maps/dir/?api=1&travelmode=driving&destination={{ urlencode($p->direccion_entrega) }}" target="_blank" rel="noopener" class="btn-secundario {{ $p->contacto_telefono ? '' : 'col-span-2' }}"><x-icono n="ubicacion" clase="size-4" /> Cómo llegar</a>
                    @endif
                </div>
                @if ($puedeEntregar)
                    <form method="POST" action="{{ route('viajes.entregar', [$v, $p]) }}" class="flex gap-2 border-t border-carbon-100 px-4 py-3" x-show="!fallo">
                        @csrf
                        <input name="recibido_por" class="campo min-w-0 flex-1" required placeholder="¿Quién recibió?" value="{{ $p->contacto_nombre }}" aria-label="Quién recibió">
                        <button class="btn-exito h-[2.625rem] shrink-0 px-4"><x-icono n="check" clase="size-4" /> Entregado</button>
                    </form>
                    <div class="border-t border-carbon-100 px-4 pb-3" x-show="!fallo"><button type="button" class="mt-2 text-xs font-semibold text-carbon-500 underline decoration-dotted hover:text-marca-700" @click="fallo = true; $nextTick(() => $refs.motivo.focus())">No se pudo entregar</button></div>
                    <form method="POST" action="{{ route('viajes.no-entregado', [$v, $p]) }}" class="space-y-2 border-t border-carbon-100 bg-marca-50/40 px-4 py-3" x-show="fallo" x-cloak
                          data-confirmar="El pedido regresa a bodega y su salida de inventario se revierte." data-titulo="¿No se entregó {{ $p->folio }}?" data-boton="Sí, regresa a bodega" data-peligro>
                        @csrf
                        <input x-ref="motivo" name="motivo" class="campo" required list="motivos-{{ $p->id }}" placeholder="¿Qué pasó?">
                        <datalist id="motivos-{{ $p->id }}"><option value="Negocio cerrado"><option value="Cliente no estaba"><option value="Cliente rechazó el producto"><option value="Dirección equivocada"><option value="No alcanzó el tiempo"></datalist>
                        <div class="flex gap-2"><button type="button" class="btn-secundario flex-1" @click="fallo = false">Volver</button><button class="btn flex-1 bg-marca-600 text-white hover:bg-marca-700">Regresa a bodega</button></div>
                    </form>
                @endif
            @endif
        </li>
    @endforeach
</ol>
<p class="mt-4 text-center text-xs text-carbon-500">Despachó {{ $v->despachador->name }}. El viaje se cierra solo al resolver la última parada.</p>
</x-layouts.app>
