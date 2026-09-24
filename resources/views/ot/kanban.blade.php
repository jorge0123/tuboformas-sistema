<x-layouts.app titulo="Tablero Kanban">
@php
    $u = auth()->user();
    $columnas = [
        'pendiente' => ['Pendiente', 'bg-carbon-400', 'Por iniciar'],
        'en_progreso' => ['En progreso', 'bg-sky-500', 'Trabajando'],
        'en_espera' => ['En espera', 'bg-amber-500', 'Repuesto, cotización, preguntas'],
        'completada' => ['Completada', 'bg-emerald-500', 'Últimos 14 días'],
    ];
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="inline-flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-carbon-200">
        <a href="{{ route('ot.index', request()->query()) }}" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold text-carbon-600 hover:text-carbon-900"><x-icono n="lista" clase="size-4" /> Lista</a>
        <a href="{{ route('ot.kanban', request()->query()) }}" class="flex items-center gap-1.5 rounded-md bg-carbon-900 px-3 py-1.5 text-sm font-semibold text-white"><x-icono n="kanban" clase="size-4" /> Kanban</a>
        <a href="{{ route('calendario') }}" class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold text-carbon-600 hover:text-carbon-900"><x-icono n="calendario" clase="size-4" /> Calendario</a>
    </div>
    @can('ot.crear')<a href="{{ route('ot.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Nueva orden</a>@endcan
</div>
<div class="mb-5">@include('ot._filtros', ['accion' => route('ot.kanban'), 'conEstado' => false])</div>

<div x-data="kanban" class="-mx-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
    <div class="grid min-w-[1100px] grid-cols-4 gap-4">
        @foreach ($columnas as $estado => [$nombre, $color, $sub])
            @php $items = $ordenes[$estado] ?? collect(); @endphp
            <section class="kanban-col flex min-h-[60vh] flex-col rounded-2xl bg-carbon-100/70 p-2 transition"
                     data-estado="{{ $estado }}"
                     @dragover.prevent="sobre($el)" @dragleave="$el.classList.remove('arrastrando-sobre')" @drop.prevent="soltar($el)">
                <header class="flex items-center gap-2 px-2 pt-1.5 pb-3">
                    <span class="size-2.5 rounded-full {{ $color }}"></span>
                    <h3 class="font-display text-sm font-bold whitespace-nowrap text-carbon-800">{{ $nombre }}</h3>
                    <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-carbon-600 ring-1 ring-carbon-200" data-contador>{{ $items->count() }}</span>
                    <span class="ml-auto hidden truncate text-[11px] text-carbon-500 2xl:inline">{{ $sub }}</span>
                </header>
                <div class="flex flex-1 flex-col gap-2" data-lista>
                    @foreach ($items as $ot)
                        @php $mueve = $estado !== 'completada' && ($u->can('ot.editar') || ($u->can('ot.ejecutar') && $ot->responsable_id === $u->id)); @endphp
                        <article class="kanban-tarjeta group rounded-xl bg-white p-3.5 shadow-sm ring-1 ring-carbon-200/80 transition hover:shadow-md {{ $mueve ? 'cursor-grab active:cursor-grabbing' : '' }}"
                                 data-id="{{ $ot->id }}" data-url="{{ route('ot.mover', $ot) }}" data-ver="{{ route('ot.show', $ot) }}"
                                 @if ($mueve) draggable="true" @dragstart="inicio($event, $el)" @dragend="$el.classList.remove('arrastrando')" @endif>
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-mono text-[11px] font-semibold text-carbon-400">{{ $ot->folio }}</span>
                                <x-ot.prioridad :prioridad="$ot->prioridad" />
                            </div>
                            <a href="{{ route('ot.show', $ot) }}" class="mt-1 block text-sm leading-snug font-semibold text-carbon-900 hover:text-marca-700">{{ $ot->titulo }}</a>
                            <p class="mt-1 flex items-center gap-1 truncate text-xs text-carbon-500"><x-icono n="maquina" clase="size-3.5 shrink-0" />{{ $ot->maquina?->etiqueta() ?? 'General' }}</p>
                            @if ($estado === 'en_espera' && $ot->motivo_espera)
                                <p class="mt-2 rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-900">{{ $ot->motivo_espera }}</p>
                            @endif
                            <div class="mt-3 flex items-center gap-2">
                                <div class="barra flex-1"><span style="width: {{ $ot->progreso }}%" class="{{ $estado === 'completada' ? '!bg-emerald-500' : '' }}"></span></div>
                                <span class="text-[11px] font-semibold tabular-nums text-carbon-600">{{ $ot->progreso }}%</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-carbon-100 pt-2.5">
                                <span class="flex items-center gap-1.5 text-xs text-carbon-600">
                                    @if ($ot->responsable)
                                        <span class="grid size-6 place-items-center rounded-full bg-carbon-900 text-[9px] font-bold text-white">{{ $ot->responsable->iniciales() }}</span>{{ strtok($ot->responsable->name, ' ') }}
                                    @else
                                        <span class="insignia-ambar py-0">Sin asignar</span>
                                    @endif
                                </span>
                                <span class="flex items-center gap-2">
                                    @if ($ot->seguimientos_count > 1)<span class="flex items-center gap-0.5 text-[11px] text-carbon-400"><x-icono n="mensaje" clase="size-3.5" />{{ $ot->seguimientos_count - 1 }}</span>@endif
                                    @if ($ot->fecha_vencimiento)
                                        @php $s = $ot->situacion(); @endphp
                                        <span class="flex items-center gap-1 text-[11px] font-semibold {{ $s === 'atrasada' ? 'text-marca-700' : ($s === 'por_vencer' ? 'text-amber-700' : 'text-carbon-500') }}">
                                            <x-icono n="calendario" clase="size-3.5" />{{ $ot->fecha_vencimiento->format('d/m') }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </article>
                    @endforeach
                    <p class="{{ $items->isEmpty() ? '' : 'hidden' }} rounded-xl border-2 border-dashed border-carbon-200 px-3 py-8 text-center text-xs text-carbon-400" data-vacio>Arrastra una tarjeta aquí</p>
                </div>
            </section>
        @endforeach
    </div>

    {{-- Motivo al pasar a "En espera" --}}
    <x-modal nombre="motivo-espera" titulo="¿Qué se está esperando?">
        <form @submit.prevent="confirmarEspera($refs.motivo.value)" class="space-y-4">
            <input x-ref="motivo" required class="campo" placeholder="Repuesto, cotización, respuesta del proveedor…" autofocus>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secundario" @click="$dispatch('cerrar-modal'); revertir()">Cancelar</button>
                <button class="btn-oscuro">Mover a En espera</button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('kanban', () => ({
        tarjeta: null, origen: null, pendiente: null,
        inicio(e, el) {
            this.tarjeta = el; this.origen = el.closest('[data-lista]');
            e.dataTransfer.effectAllowed = 'move';
            requestAnimationFrame(() => el.classList.add('arrastrando'));
        },
        sobre(col) { if (this.tarjeta) col.classList.add('arrastrando-sobre'); },
        soltar(col) {
            col.classList.remove('arrastrando-sobre');
            const t = this.tarjeta; this.tarjeta = null;
            if (!t) return;
            const estado = col.dataset.estado;
            const lista = col.querySelector('[data-lista]');
            if (lista === this.origen) return;
            if (estado === 'completada') {
                // Completar pide el trabajo realizado: se hace en la ficha de la orden.
                window.location = t.dataset.ver + '?completar=1';
                return;
            }
            this.mover(t, lista);
            if (estado === 'en_espera') {
                this.pendiente = t;
                this.$dispatch('abrir-modal', 'motivo-espera');
                return;
            }
            this.guardar(t, estado);
        },
        mover(t, lista) {
            lista.insertBefore(t, lista.querySelector('[data-vacio]'));
            this.contar();
        },
        revertir() {
            if (this.pendiente) { this.mover(this.pendiente, this.origen); this.pendiente = null; }
        },
        confirmarEspera(motivo) {
            this.$dispatch('cerrar-modal');
            const t = this.pendiente; this.pendiente = null;
            this.guardar(t, 'en_espera', motivo);
        },
        async guardar(t, estado, motivo = null) {
            try {
                await api(t.dataset.url, { method: 'POST', body: { estado, motivo_espera: motivo } });
                avisar('Orden movida a ' + { pendiente: 'Pendiente', en_progreso: 'En progreso', en_espera: 'En espera' }[estado] + '.');
            } catch (e) {
                this.mover(t, this.origen);
                avisar(e.message, 'error');
            }
        },
        contar() {
            this.$root.querySelectorAll('.kanban-col').forEach(c => {
                const n = c.querySelectorAll('.kanban-tarjeta').length;
                c.querySelector('[data-contador]').textContent = n;
                c.querySelector('[data-vacio]').classList.toggle('hidden', n > 0);
            });
        },
    }));
});
</script>
@endpush
</x-layouts.app>
