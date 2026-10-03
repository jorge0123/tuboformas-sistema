<x-layouts.app titulo="Viajes de entrega">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-carbon-600">Un camión con su piloto lleva varios pedidos en orden. {{ $listos ? "Hay $listos ".($listos === 1 ? 'pedido listo' : 'pedidos listos').' para salir.' : '' }}</p>
    @can('pedidos.preparar')<a href="{{ route('viajes.create') }}" class="btn-primario"><x-icono n="camion" clase="size-4" /> Armar viaje @if ($listos)<span class="rounded-full bg-white/25 px-1.5 text-xs">{{ $listos }}</span>@endif</a>@endcan
</div>

<h3 class="mb-2 text-xs font-bold tracking-wide text-carbon-500 uppercase">En ruta</h3>
<div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($enRuta as $v)
        <a href="{{ route('viajes.show', $v) }}" class="tarjeta aparecer block p-4 transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-center gap-3">
                <span class="relative grid size-10 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600"><x-icono n="camion" clase="size-5" />
                    <span class="absolute -top-0.5 -right-0.5 size-2.5 animate-pulse rounded-full bg-amber-500 ring-2 ring-white"></span></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $v->vehiculo->codigo }} · {{ $v->piloto->name }}</p>
                    <p class="text-xs text-carbon-500"><span class="font-mono">{{ $v->folio }}</span> · salió {{ $v->salida_at->diffForHumans() }}</p>
                </div>
                <span class="font-display text-lg font-extrabold tabular-nums">{{ $v->entregados }}/{{ $v->pedidos_count }}</span>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-carbon-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $v->pedidos_count ? round($v->entregados / $v->pedidos_count * 100) : 0 }}%"></div></div>
        </a>
    @empty
        <p class="rounded-xl border-2 border-dashed border-carbon-200 px-4 py-8 text-center text-sm text-carbon-500 md:col-span-2 xl:col-span-3">No hay camiones en ruta.</p>
    @endforelse
</div>

<h3 class="mb-2 text-xs font-bold tracking-wide text-carbon-500 uppercase">Terminados</h3>
<div class="tarjeta overflow-hidden">
    @if ($terminados->isEmpty())
        <x-vacio icono="camion" titulo="Aún no hay viajes terminados" texto="" />
    @else
    <div class="overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Viaje</th><th>Piloto</th><th>Salió</th><th>Regresó</th><th class="text-right">Entregados</th></tr></thead>
            <tbody class="divide-y divide-carbon-50">
            @foreach ($terminados as $v)
                <tr class="cursor-pointer" onclick="location='{{ route('viajes.show', $v) }}'">
                    <td><p class="font-semibold">{{ $v->vehiculo->codigo }} · {{ $v->vehiculo->nombre }}</p><p class="font-mono text-xs text-carbon-500">{{ $v->folio }}</p></td>
                    <td>{{ $v->piloto->name }}</td>
                    <td class="whitespace-nowrap">{{ $v->salida_at->format('d/m H:i') }}</td>
                    <td class="whitespace-nowrap">{{ $v->regreso_at?->format('d/m H:i') }}</td>
                    <td class="tabla-num"><span class="{{ $v->entregados < $v->pedidos_count ? 'text-marca-700' : 'text-emerald-700' }} font-semibold">{{ $v->entregados }}/{{ $v->pedidos_count }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $terminados->links() }}
    @endif
</div>
</x-layouts.app>
