{{-- Bitácora como línea de tiempo. $registros, $mostrarMaquina --}}
@if ($registros->isEmpty())
    <x-vacio icono="libro" titulo="Sin registros en la bitácora" texto="Al completar una orden de trabajo de esta máquina, el trabajo se registra aquí automáticamente." />
@else
<ol class="relative px-5 py-4">
    @foreach ($registros as $b)
        @php
            $color = ['preventivo' => 'bg-emerald-500', 'correctivo' => 'bg-marca-600', 'predictivo' => 'bg-violet-500', 'mejora' => 'bg-sky-500', 'proyecto' => 'bg-amber-500'][$b->tipo] ?? 'bg-carbon-400';
        @endphp
        <li class="relative flex gap-4 pb-6 last:pb-0">
            @unless ($loop->last)<span class="absolute top-4 bottom-0 left-[5px] w-px bg-carbon-200"></span>@endunless
            <span class="relative mt-1.5 size-[11px] shrink-0 rounded-full ring-4 ring-white {{ $color }}"></span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                    <span class="font-semibold text-carbon-900">@fecha($b->fecha)</span>
                    <span class="text-carbon-300">•</span>
                    <span class="font-medium text-carbon-600">{{ \App\Models\OrdenTrabajo::TIPOS[$b->tipo] ?? $b->tipo }}</span>
                    @if ($b->componente)<span class="insignia-gris">{{ $b->componente }}</span>@endif
                    @if ($b->garantia)<span class="insignia-ambar"><x-icono n="escudo" clase="size-3" /> Reclamo por garantía</span>@endif
                    @if ($b->orden)<a href="{{ route('ot.show', $b->orden) }}" class="insignia-azul hover:underline"><x-icono n="portapapeles" clase="size-3" />{{ $b->orden->folio }}</a>@endif
                </div>
                @if ($mostrarMaquina ?? false)
                    <a href="{{ route('maquinas.show', ['maquina' => $b->maquina_id, 'tab' => 'bitacora']) }}" class="mt-1 block text-xs font-semibold text-marca-700 hover:underline">{{ $b->maquina->etiqueta() }}</a>
                @endif
                <p class="mt-1 text-sm font-semibold text-carbon-900">{{ $b->trabajo_realizado }}</p>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-carbon-500">
                    <span class="flex items-center gap-1"><x-icono n="usuario" clase="size-3.5" />{{ $b->nombreResponsable() }}</span>
                    @if ($b->horas)<span class="flex items-center gap-1"><x-icono n="reloj" clase="size-3.5" />@num($b->horas) h</span>@endif
                    @if ($b->proveedor)<span class="flex items-center gap-1"><x-icono n="camion" clase="size-3.5" />{{ $b->proveedor->nombre }}</span>@endif
                    @if ($b->costo)@can('inventario.ver_costos')<span class="flex items-center gap-1"><x-icono n="etiqueta" clase="size-3.5" />@dinero($b->costo)</span>@endcan @endif
                    @if ($b->horometro)<span class="flex items-center gap-1"><x-icono n="tablero" clase="size-3.5" />Horómetro @num($b->horometro)</span>@endif
                </div>
                @if ($b->comentarios)<p class="mt-2 rounded-lg bg-carbon-50 px-3 py-2 text-sm whitespace-pre-line text-carbon-600">{{ $b->comentarios }}</p>@endif
                @can('bitacora.editar')
                    <a href="{{ route('bitacora.edit', $b) }}" class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-carbon-500 hover:text-carbon-800"><x-icono n="lapiz" clase="size-3" /> Editar</a>
                @endcan
            </div>
        </li>
    @endforeach
</ol>
@endif
