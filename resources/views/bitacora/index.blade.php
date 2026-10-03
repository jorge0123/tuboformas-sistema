<x-layouts.app titulo="Bitácora de mantenimiento">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <x-filtros :accion="route('bitacora.index')" placeholder="Trabajo, componente o comentario">
        <select name="maquina" class="campo w-auto max-w-56">
            <option value="">Todas las máquinas</option>
            @foreach ($maquinas as $m)<option value="{{ $m->id }}" @selected(request('maquina') == $m->id)>{{ $m->codigo ? $m->codigo.' · ' : '' }}{{ $m->nombre }}</option>@endforeach
        </select>
        <select name="tipo" class="campo w-auto">
            <option value="">Todo tipo</option>
            @foreach (\App\Models\OrdenTrabajo::TIPOS as $k => $v)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>@endforeach
        </select>
        <input type="date" name="desde" value="{{ request('desde') }}" class="campo w-auto" title="Desde" onchange="this.form.requestSubmit()">
        <input type="date" name="hasta" value="{{ request('hasta') }}" class="campo w-auto" title="Hasta" onchange="this.form.requestSubmit()">
    </x-filtros>
    <div class="flex flex-wrap gap-2">
        @can('reportes.exportar')<a href="{{ route('bitacora.exportar', request()->query()) }}" class="btn-secundario"><x-icono n="descargar" clase="size-4" /> Excel</a>@endcan
        @can('bitacora.crear')<a href="{{ route('bitacora.create') }}" class="btn-primario"><x-icono n="mas" clase="size-4" /> Registrar trabajo</a>@endcan
    </div>
</div>
<div class="tarjeta">
    @include('bitacora._linea_tiempo', ['registros' => $registros, 'mostrarMaquina' => true])
    {{ $registros->links() }}
</div>
</x-layouts.app>
