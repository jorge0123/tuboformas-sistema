<x-layouts.app :titulo="'Conteo '.$conteo->folio">
<x-slot:migas><a href="{{ route('conteos.index') }}" class="hover:text-carbon-800">Conteos físicos</a><x-icono n="derecha" clase="size-3" />{{ $conteo->folio }}</x-slot:migas>
@php $abierto = $conteo->estado === 'abierto'; $puedeContar = $abierto && auth()->user()->can('conteos.registrar'); @endphp

<div class="tarjeta p-5">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex items-center gap-2"><span class="font-mono text-sm font-bold text-carbon-400">{{ $conteo->folio }}</span>
                <span class="{{ ['abierto' => 'insignia-azul', 'aplicado' => 'insignia-verde', 'cancelado' => 'insignia-gris'][$conteo->estado] }}">{{ \App\Models\Conteo::ESTADOS[$conteo->estado] }}</span></div>
            <h2 class="mt-1 font-display text-2xl font-extrabold">Conteo de repuestos</h2>
            @if ($conteo->notas)<p class="text-sm text-carbon-600">{{ $conteo->notas }}</p>@endif
            <p class="text-sm text-carbon-500">Abierto por {{ $conteo->user->name }} el @fecha($conteo->fecha){{ $conteo->aplicadoPor ? ' · aplicado por '.$conteo->aplicadoPor->name.' el '.$conteo->aplicado_at->format('d/m/Y H:i') : '' }}</p>
        </div>
        @if ($abierto)
            @can('conteos.gestionar')
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('conteos.cancelar', $conteo) }}" data-confirmar="No se modificará ninguna existencia." data-titulo="Cancelar conteo" data-boton="Cancelar conteo" data-peligro>@csrf<button class="btn-peligro">Cancelar conteo</button></form>
                <form method="POST" action="{{ route('conteos.aplicar', $conteo) }}" data-confirmar="La existencia de los repuestos contados quedará igual a lo contado. Los que no se contaron no se tocan." data-titulo="Aplicar conteo" data-boton="Aplicar ajustes">@csrf
                    <button class="btn-exito"><x-icono n="check" clase="size-4" /> Aplicar diferencias</button>
                </form>
            </div>
            @endcan
        @endif
    </div>
    <div class="mt-5 grid grid-cols-3 gap-4 border-t border-carbon-100 pt-5">
        <div><p class="dato-etiqueta">Repuestos</p><p class="font-display text-2xl font-extrabold">{{ $resumen['total'] }}</p></div>
        <div><p class="dato-etiqueta">Contados</p><p class="font-display text-2xl font-extrabold text-sky-700">{{ $resumen['contadas'] }}</p></div>
        <div><p class="dato-etiqueta">Con diferencia</p><p class="font-display text-2xl font-extrabold text-marca-700">{{ $resumen['diferencias'] }}</p></div>
    </div>
</div>

<div class="my-5 flex flex-wrap items-center gap-2">
    <form method="GET" class="relative min-w-56 flex-1 sm:max-w-xs">
        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-carbon-400"><x-icono n="buscar" clase="size-4" /></span>
        <input name="q" value="{{ request('q') }}" class="campo pl-9" placeholder="Buscar repuesto">
        @if (request('ver'))<input type="hidden" name="ver" value="{{ request('ver') }}">@endif
    </form>
    @foreach (['' => 'Todos', 'pendientes' => 'Sin contar', 'diferencias' => 'Con diferencia'] as $k => $v)
        <a href="{{ request()->fullUrlWithQuery(['ver' => $k ?: null]) }}" class="btn btn-sm {{ request('ver', '') === $k ? 'bg-carbon-900 text-white' : 'btn-secundario' }}">{{ $v }}</a>
    @endforeach
</div>

<div class="tarjeta overflow-hidden" x-data="{ guardar(el, id) { const v = el.value === '' ? null : el.value;
        api('{{ route('conteos.capturar', $conteo) }}', { method: 'POST', body: { linea_id: id, cantidad: v } })
          .then(r => { el.classList.add('ring-emerald-400'); setTimeout(() => el.classList.remove('ring-emerald-400'), 900); el.dispatchEvent(new CustomEvent('guardado', { detail: r.diferencia })) })
          .catch(e => avisar(e.message, 'error')) } }">
    <div class="divide-y divide-carbon-100">
        @forelse ($lineas as $l)
            @php $dif = $l->diferencia(); @endphp
            <div class="grid grid-cols-12 items-center gap-3 px-4 py-3" x-data="{ dif: @js($dif) }">
                <div class="col-span-12 min-w-0 sm:col-span-5">
                    <p class="truncate font-semibold text-carbon-900">{{ $l->producto->nombre }}</p>
                    <p class="font-mono text-xs text-carbon-500">{{ $l->producto->codigo }}</p>
                </div>
                @if ($verSistema)
                    <div class="col-span-4 text-right sm:col-span-2"><p class="dato-etiqueta">Sistema</p><p class="font-semibold tabular-nums">@num($l->cantidad_sistema)</p></div>
                @endif
                <div class="{{ $verSistema ? 'col-span-5' : 'col-span-8' }} sm:col-span-3">
                    @if ($puedeContar)
                        <div class="flex items-center gap-1.5">
                            <input type="number" step="any" min="0" inputmode="decimal" value="{{ $l->cantidad_contada !== null ? (float) $l->cantidad_contada : '' }}"
                                   class="campo text-right font-semibold transition" placeholder="Contado"
                                   @change="guardar($el, {{ $l->id }})" @guardado="dif = $event.detail">
                            <span class="text-xs text-carbon-500">{{ $l->producto->unidad->abreviatura }}</span>
                        </div>
                    @else
                        <p class="dato-etiqueta">Contado</p><p class="font-semibold tabular-nums">{{ $l->cantidad_contada !== null ? \App\Support\Formato::numero($l->cantidad_contada) : '—' }}</p>
                    @endif
                </div>
                @if ($verSistema)
                <div class="col-span-3 text-right sm:col-span-2">
                    <p class="dato-etiqueta">Diferencia</p>
                    <p class="font-display font-extrabold tabular-nums" :class="dif === null ? 'text-carbon-300' : (dif == 0 ? 'text-emerald-600' : (dif > 0 ? 'text-sky-700' : 'text-marca-700'))"
                       x-text="dif === null ? '—' : (dif > 0 ? '+' : '') + Number(dif).toLocaleString('es-GT')"></p>
                </div>
                @endif
            </div>
        @empty
            <x-vacio icono="conteo" titulo="Sin repuestos en este filtro" />
        @endforelse
    </div>
</div>
</x-layouts.app>
