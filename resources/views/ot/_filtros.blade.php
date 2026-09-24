{{-- Filtros compartidos por lista y Kanban. $accion, $conEstado --}}
<x-filtros :accion="$accion" placeholder="Folio, tarea o máquina">
    @if ($conEstado)
    <select name="estado" class="campo w-auto">
        <option value="">Abiertas</option>
        @foreach (\App\Models\OrdenTrabajo::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $v }}</option>@endforeach
    </select>
    @endif
    <select name="situacion" class="campo w-auto">
        <option value="">Toda situación</option>
        @foreach (['atrasada', 'por_vencer', 'en_tiempo', 'sin_fecha'] as $k)<option value="{{ $k }}" @selected(request('situacion') === $k)>{{ \App\Models\OrdenTrabajo::SITUACIONES[$k] }}</option>@endforeach
    </select>
    <select name="tipo" class="campo w-auto">
        <option value="">Todo tipo</option>
        @foreach (\App\Models\OrdenTrabajo::TIPOS as $k => $v)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>@endforeach
    </select>
    <select name="prioridad" class="campo w-auto">
        <option value="">Toda prioridad</option>
        @foreach (\App\Models\OrdenTrabajo::PRIORIDADES as $k => $v)<option value="{{ $k }}" @selected(request('prioridad') === $k)>{{ $v }}</option>@endforeach
    </select>
    <select name="especialidad" class="campo w-auto">
        <option value="">Toda especialidad</option>
        @foreach ($especialidades as $e)<option value="{{ $e->id }}" @selected(request('especialidad') == $e->id)>{{ $e->nombre }}</option>@endforeach
    </select>
    @can('ot.ver_todas')
    <select name="responsable" class="campo w-auto">
        <option value="">Todos los responsables</option>
        @foreach ($tecnicos as $t)<option value="{{ $t->id }}" @selected(request('responsable') == $t->id)>{{ $t->name }}</option>@endforeach
    </select>
    @endcan
</x-filtros>
