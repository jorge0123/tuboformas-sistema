<x-layouts.app :titulo="$cliente->nombre">
<x-slot:migas><a href="{{ route('clientes.index') }}" class="hover:text-carbon-800">Clientes</a><x-icono n="derecha" clase="size-3" />{{ $cliente->nombre }}</x-slot:migas>

<div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
    <section class="tarjeta h-fit">
        <div class="p-5">
            <span class="grid size-12 place-items-center rounded-full bg-carbon-900 font-display text-lg font-extrabold text-white">{{ mb_strtoupper(mb_substr($cliente->nombre, 0, 2)) }}</span>
            <h2 class="mt-3 font-display text-xl font-extrabold">{{ $cliente->nombre }}</h2>
            <p class="text-sm text-carbon-500">{{ collect([$cliente->nit ? 'NIT '.$cliente->nit : null, $cliente->municipio])->filter()->join(' · ') }}</p>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach (['Contacto' => $cliente->contacto, 'Dirección' => $cliente->direccion, 'Correo' => $cliente->email] as $k => $v)
                    @if ($v)<div><dt class="dato-etiqueta">{{ $k }}</dt><dd class="font-medium">{{ $v }}</dd></div>@endif
                @endforeach
                @if ($cliente->notas)<div><dt class="dato-etiqueta">Notas</dt><dd class="whitespace-pre-line text-carbon-700">{{ $cliente->notas }}</dd></div>@endif
            </dl>
            <div class="mt-5 flex flex-wrap gap-2">
                @if ($cliente->telefono)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $cliente->telefono) }}" class="btn-secundario flex-1"><x-icono n="telefono" clase="size-4" /> Llamar</a>@endif
                @can('clientes.gestionar')<a href="{{ route('clientes.edit', $cliente) }}" class="btn-secundario flex-1"><x-icono n="lapiz" clase="size-4" /> Editar</a>@endcan
            </div>
            @can('pedidos.crear')<a href="{{ route('pedidos.create', ['cliente' => $cliente->id]) }}" class="btn-primario mt-2 w-full"><x-icono n="mas" clase="size-4" /> Nuevo pedido</a>@endcan
        </div>
    </section>

    <section class="tarjeta xl:col-span-2">
        <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Pedidos</h3><span class="text-xs text-carbon-500">Últimos 30</span></div>
        @forelse ($pedidos as $p)
            <a href="{{ route('pedidos.show', $p) }}" class="flex items-center gap-3 border-b border-carbon-50 px-4 py-3 transition last:border-0 hover:bg-carbon-50">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold"><span class="font-mono">{{ $p->folio }}</span> · {{ $p->lineas_count }} productos</p>
                    <p class="text-xs text-carbon-500">Entrega {{ $p->fecha_entrega->format('d/m/Y') }} · {{ $p->vendedor->name }}</p>
                </div>
                <x-pedido.estado :estado="$p->estado" />
            </a>
        @empty
            <x-vacio icono="camion" titulo="Sin pedidos todavía" texto="" />
        @endforelse
    </section>
</div>
</x-layouts.app>
