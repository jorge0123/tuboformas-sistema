<x-layouts.app :titulo="$herramienta->nombre">
<x-slot:migas><a href="{{ route('herramientas.index') }}" class="hover:text-carbon-800">Herramientas</a><x-icono n="derecha" clase="size-3" />{{ $herramienta->codigo }}</x-slot:migas>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6">
        <section class="tarjeta overflow-hidden">
            @if ($herramienta->foto)
                <img src="{{ Storage::url($herramienta->foto) }}" alt="" class="aspect-video w-full object-cover">
            @else
                <div class="grid aspect-video place-items-center bg-carbon-100 text-carbon-300"><x-icono n="martillo" clase="size-16" /></div>
            @endif
            <div class="p-5">
                <div class="flex items-center gap-2"><span class="insignia-oscura font-mono">{{ $herramienta->codigo }}</span><x-herramienta.estado :estado="$herramienta->estado" /></div>
                <h2 class="mt-2 font-display text-xl font-extrabold">{{ $herramienta->nombre }}</h2>
                <dl class="mt-4 grid grid-cols-2 gap-4">
                    @foreach (['Categoría' => $herramienta->categoria, 'Marca' => $herramienta->marca, 'Modelo' => $herramienta->modelo, 'Serie' => $herramienta->serie, 'Compra' => $herramienta->fecha_compra?->format('d/m/Y')] as $k => $v)
                        <div><dt class="dato-etiqueta">{{ $k }}</dt><dd class="dato-valor">{{ $v ?? '—' }}</dd></div>
                    @endforeach
                    @can('inventario.ver_costos')<div><dt class="dato-etiqueta">Costo</dt><dd class="dato-valor">@dinero($herramienta->costo)</dd></div>@endcan
                </dl>
                @if ($herramienta->notas)<p class="mt-4 text-sm text-carbon-600">{{ $herramienta->notas }}</p>@endif
                @can('herramientas.gestionar')
                    <a href="{{ route('herramientas.edit', $herramienta) }}" class="btn-secundario mt-5 w-full"><x-icono n="lapiz" clase="size-4" /> Editar</a>
                @endcan
            </div>
        </section>
    </div>

    <div class="space-y-6 lg:col-span-2">
        @can('herramientas.asignar')
        <section class="tarjeta">
            @if ($herramienta->estado === 'asignada')
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Recibir herramienta</h3><span class="text-sm text-carbon-500">A cargo de <b class="text-carbon-800">{{ $herramienta->asignadaA?->name }}</b></span></div>
                <form method="POST" action="{{ route('herramientas.devolver', $herramienta) }}" class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                    @csrf
                    <div>
                        <label class="etiqueta">¿En qué estado la devuelve?</label>
                        <select name="estado_devolucion" class="campo">@foreach (\App\Models\Herramienta::CONDICIONES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                    </div>
                    <div class="sm:col-span-2"><label class="etiqueta">Notas</label><input name="notas" class="campo" placeholder="Opcional"></div>
                    <div class="flex justify-end sm:col-span-3"><button class="btn-oscuro"><x-icono n="deshacer" clase="size-4" /> Registrar devolución</button></div>
                </form>
            @elseif ($herramienta->estado === 'disponible')
                <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Entregar a un técnico</h3></div>
                <form method="POST" action="{{ route('herramientas.asignar', $herramienta) }}" class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
                    @csrf
                    <div>
                        <label class="etiqueta">Técnico</label>
                        <select name="user_id" required class="campo"><option value="">Elige…</option>@foreach ($tecnicos as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="etiqueta">Estado al entregar</label>
                        <select name="estado_entrega" class="campo">@foreach (\App\Models\Herramienta::CONDICIONES as $k => $v)@continue($k === 'perdido')<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                    </div>
                    <div><label class="etiqueta">Notas</label><input name="notas" class="campo" placeholder="Opcional"></div>
                    <div class="flex justify-end sm:col-span-3"><button class="btn-primario"><x-icono n="usuario" clase="size-4" /> Entregar</button></div>
                </form>
            @else
                <p class="flex items-center gap-2 p-5 text-sm text-carbon-600"><x-icono n="alerta" clase="size-4 text-amber-500" /> La herramienta está {{ mb_strtolower(\App\Models\Herramienta::ESTADOS[$herramienta->estado]) }}. Cámbiala a disponible desde Editar para poder entregarla.</p>
            @endif
        </section>
        @endcan

        <section class="tarjeta">
            <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Historial de entregas</h3></div>
            @forelse ($herramienta->asignaciones as $a)
                <div class="flex items-start gap-4 border-b border-carbon-50 px-5 py-3.5 last:border-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-carbon-100 text-xs font-bold">{{ $a->user->iniciales() }}</span>
                    <div class="min-w-0 flex-1 text-sm">
                        <p><b>{{ $a->user->name }}</b> <span class="text-carbon-500">recibió de {{ $a->entregadoPor->name }} · {{ $a->entregado_at->format('d/m/Y H:i') }} · {{ \App\Models\Herramienta::CONDICIONES[$a->estado_entrega] ?? $a->estado_entrega }}</span></p>
                        @if ($a->devuelto_at)
                            <p class="text-carbon-500">Devuelta a {{ $a->recibidoPor?->name }} · {{ $a->devuelto_at->format('d/m/Y H:i') }} ·
                                <span class="{{ $a->estado_devolucion === 'bueno' ? 'text-emerald-700' : 'text-marca-700' }} font-semibold">{{ \App\Models\Herramienta::CONDICIONES[$a->estado_devolucion] ?? '' }}</span></p>
                        @else
                            <span class="insignia-azul mt-1">En su poder</span>
                        @endif
                        @if ($a->notas)<p class="mt-1 text-xs text-carbon-500">{{ $a->notas }}</p>@endif
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-carbon-500">Nunca se ha entregado.</p>
            @endforelse
        </section>
    </div>
</div>
</x-layouts.app>
