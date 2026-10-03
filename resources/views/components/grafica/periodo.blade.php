@props(['desde', 'hasta'])
<form method="GET" class="flex flex-wrap items-center gap-2" data-filtro-vivo>
    @foreach ([30 => '30 días', 90 => '90 días', 365 => '12 meses'] as $d => $t)
        <a href="{{ request()->url() }}?desde={{ today()->subDays($d - 1)->format('Y-m-d') }}&hasta={{ today()->format('Y-m-d') }}"
           class="btn btn-sm {{ $desde->toDateString() === today()->subDays($d - 1)->toDateString() && $hasta->isToday() ? 'bg-carbon-900 text-white' : 'btn-secundario' }}">{{ $t }}</a>
    @endforeach
    <span class="mx-1 h-5 w-px bg-carbon-200"></span>
    <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="campo w-auto py-1.5">
    <span class="text-sm text-carbon-400">a</span>
    <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="campo w-auto py-1.5">
    <button class="btn-oscuro btn-sm">Aplicar</button>
    <button type="button" class="btn-secundario btn-sm" onclick="window.print()"><x-icono n="imprimir" clase="size-4" /> Imprimir</button>
</form>
