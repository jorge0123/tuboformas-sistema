<x-layouts.app titulo="Notificaciones">
<div class="mx-auto max-w-3xl">
    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('notificaciones.leer-todas') }}">@csrf<button class="btn-secundario"><x-icono n="check" clase="size-4" /> Marcar todas como leídas</button></form>
    </div>
    <div class="tarjeta overflow-hidden">
        @forelse ($notificaciones as $n)
            <a href="{{ route('notificaciones.abrir', $n->id) }}" class="flex gap-4 border-b border-carbon-50 px-5 py-4 transition last:border-0 hover:bg-carbon-50 {{ $n->read_at ? '' : 'bg-marca-50/30' }}">
                <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $n->read_at ? 'bg-carbon-100 text-carbon-400' : 'bg-marca-50 text-marca-600' }}"><x-icono n="campana" clase="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-carbon-900">{{ $n->data['titulo'] ?? '' }}</p>
                    <p class="text-sm text-carbon-600">{{ $n->data['mensaje'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-carbon-400">{{ $n->created_at->diffForHumans() }} · {{ $n->created_at->format('d/m/Y H:i') }}</p>
                </div>
                @unless ($n->read_at)<span class="mt-2 size-2 shrink-0 rounded-full bg-marca-600"></span>@endunless
            </a>
        @empty
            <x-vacio icono="campana" titulo="Sin notificaciones" />
        @endforelse
        {{ $notificaciones->links() }}
    </div>
</div>
</x-layouts.app>
