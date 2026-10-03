<x-layouts.app titulo="Pedidos">
@php
    $pestanas = ['activos' => 'En curso', 'nuevo' => 'Por armar', 'preparando' => 'Armando', 'listo' => 'Listos', 'en_ruta' => 'En ruta', 'entregado' => 'Entregados', 'cancelado' => 'Cancelados'];
    $cuenta = fn ($k) => $k === 'activos' ? collect(\App\Models\Pedido::ACTIVOS)->sum(fn ($e) => $conteos[$e] ?? 0) : ($conteos[$k] ?? 0);
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('pedidos.index')" placeholder="Folio, cliente u OC">
        <input type="hidden" name="ver" value="{{ $vista }}">
        <select name="cuando" class="campo w-auto">
            <option value="">Cualquier fecha</option>
            @foreach (['hoy' => 'Entregar hoy', 'manana' => 'Entregar mañana', 'semana' => 'Próximos 7 días', 'atrasados' => 'Atrasados'] as $k => $v)
                <option value="{{ $k }}" @selected(request('cuando') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        @if ($vendedores->count() > 1)
            <select name="vendedor" class="campo w-auto">
                <option value="">Todos los vendedores</option>
                @foreach ($vendedores as $v)<option value="{{ $v->id }}" @selected(request('vendedor') == $v->id)>{{ $v->name }}</option>@endforeach
            </select>
        @endif
    </x-filtros>
    @can('pedidos.crear')<a href="{{ route('pedidos.create') }}" class="btn-primario max-lg:hidden"><x-icono n="mas" clase="size-4" /> Nuevo pedido</a>@endcan
</div>

@if ($vista === 'listo' && ($conteos['listo'] ?? 0) && auth()->user()->can('pedidos.preparar'))
    <a href="{{ route('viajes.create') }}" class="mb-4 flex items-center gap-3 rounded-xl bg-carbon-900 px-4 py-3 text-sm text-white transition hover:bg-carbon-800">
        <x-icono n="camion" clase="size-5 text-marca-400" />
        <span class="min-w-0 flex-1"><b>¿Van en el mismo camión?</b> <span class="text-carbon-300">Arma un viaje con varios pedidos y sus paradas en orden.</span></span>
        <x-icono n="derecha" clase="size-4" />
    </a>
@endif
{{-- Estados: cada pestaña dice cuántos hay --}}
<nav class="pestanas mb-4" aria-label="Estado del pedido">
    @foreach ($pestanas as $k => $v)
        @php $n = $cuenta($k); @endphp
        <a href="{{ route('pedidos.index', ['ver' => $k] + request()->except('ver', 'page')) }}" class="pestana flex items-center gap-1.5 {{ $vista === $k ? 'activa' : '' }}">
            {{ $v }}
            @if ($n && ! in_array($k, ['entregado', 'cancelado']))
                <span class="rounded-full px-1.5 text-[11px] font-bold tabular-nums {{ $vista === $k ? 'bg-marca-600 text-white' : ($k === 'nuevo' ? 'bg-sky-100 text-sky-800' : 'bg-carbon-100 text-carbon-600') }}">{{ $n }}</span>
            @endif
        </a>
    @endforeach
</nav>

<div class="tarjeta overflow-hidden">
    @if ($pedidos->isEmpty())
        <x-vacio icono="camion" :titulo="$vista === 'activos' ? 'No hay pedidos en curso' : 'Sin pedidos aquí'"
                 texto="Cuando ventas ingrese un pedido aparecerá en esta lista y bodega recibirá el aviso." />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead>
                <tr><th>Pedido</th><th>Entrega</th><th>Estado</th><th>Armado</th><th>Bodega</th><th data-movil="ocultar">Vendedor</th></tr>
            </thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($pedidos as $p)
                @php
                    $dias = today()->diffInDays($p->fecha_entrega, false);
                    $cuando = match (true) { $p->atrasado() => ['Atrasado', 'insignia-roja'], $dias == 0 => ['Hoy', 'insignia-ambar'], $dias == 1 => ['Mañana', 'insignia-gris'], default => [null, null] };
                @endphp
                <tr class="cursor-pointer" onclick="location='{{ route('pedidos.show', $p) }}'">
                    <td class="max-w-md">
                        <p class="flex items-center gap-2 font-semibold text-carbon-900">
                            @if ($p->prioridad === 'urgente')<span class="insignia-roja shrink-0"><x-icono n="rayo" clase="size-3" /> Urgente</span>@endif
                            <span class="truncate">{{ $p->cliente->nombre }}</span>
                        </p>
                        <p class="truncate text-xs text-carbon-500"><span class="font-mono font-semibold">{{ $p->folio }}</span> · {{ $p->lineas_count }} {{ $p->lineas_count === 1 ? 'producto' : 'productos' }} · {{ \App\Models\Pedido::TIPOS_ENTREGA[$p->tipo_entrega] }}</p>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="font-medium {{ $p->atrasado() ? 'text-marca-700' : 'text-carbon-800' }}">{{ $p->fecha_entrega->translatedFormat('D d M') }}</span>
                        @if ($p->jornada)<span class="text-xs text-carbon-500">· {{ \App\Models\Pedido::JORNADAS[$p->jornada] }}</span>@endif
                        @if ($cuando[0] && $p->estaActivo())<span class="{{ $cuando[1] }} ml-1">{{ $cuando[0] }}</span>@endif
                    </td>
                    <td><x-pedido.estado :estado="$p->estado" /></td>
                    <td>
                        @if (in_array($p->estado, ['preparando', 'nuevo']))
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-carbon-100"><div class="h-full rounded-full bg-sky-500" style="width: {{ $p->lineas_count ? round($p->armadas / $p->lineas_count * 100) : 0 }}%"></div></div>
                                <span class="text-xs tabular-nums text-carbon-500">{{ $p->armadas }}/{{ $p->lineas_count }}</span>
                            </div>
                        @elseif ($p->estado !== 'cancelado')
                            <span class="flex items-center gap-1 text-xs font-semibold text-emerald-700"><x-icono n="check" clase="size-3.5" /> Completo</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $p->preparador?->name ?? ($p->estado === 'nuevo' ? 'Sin tomar' : '—') }}</td>
                    <td class="text-sm text-carbon-600">{{ $p->vendedor->name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $pedidos->links() }}
    @endif
</div>
</x-layouts.app>
