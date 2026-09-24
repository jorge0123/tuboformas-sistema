<x-layouts.app titulo="Conteos físicos">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="max-w-2xl text-sm text-carbon-600">Un conteo compara lo que hay en físico contra el sistema. Al aplicarlo, las diferencias se registran como ajustes y la existencia queda igual a lo contado.</p>
    @can('conteos.gestionar')<a href="{{ route('conteos.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nuevo conteo</a>@endcan
</div>
<div class="tarjeta overflow-hidden">
    @if ($conteos->isEmpty())
        <x-vacio icono="conteo" titulo="Sin conteos" texto="Abre un conteo por bodega para verificar la existencia." />
    @else
    <table class="tabla">
        <thead><tr><th>Conteo</th><th>Bodega</th><th>Fecha</th><th class="w-48">Avance</th><th>Abrió</th><th>Estado</th></tr></thead>
        <tbody class="divide-y divide-carbon-50">
        @foreach ($conteos as $c)
            @php $pct = $c->lineas_count ? round($c->contadas / $c->lineas_count * 100) : 0; @endphp
            <tr class="cursor-pointer" onclick="location='{{ route('conteos.show', $c) }}'">
                <td class="font-mono font-semibold">{{ $c->folio }}</td>
                <td>{{ $c->bodega->nombre }}</td>
                <td class="tabular-nums">@fecha($c->fecha)</td>
                <td><div class="flex items-center gap-2"><div class="barra flex-1"><span style="width: {{ $pct }}%"></span></div><span class="text-xs font-semibold tabular-nums">{{ $c->contadas }}/{{ $c->lineas_count }}</span></div></td>
                <td>{{ $c->user->name }}</td>
                <td><span class="{{ ['abierto' => 'insignia-azul', 'aplicado' => 'insignia-verde', 'cancelado' => 'insignia-gris'][$c->estado] }}">{{ \App\Models\Conteo::ESTADOS[$c->estado] }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $conteos->links() }}
    @endif
</div>
</x-layouts.app>
