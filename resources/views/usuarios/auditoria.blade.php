<x-layouts.app titulo="Auditoría">
<div class="mb-5">
    <x-filtros :accion="route('auditoria')" placeholder="Descripción o entidad">
        <select name="usuario" class="campo w-auto"><option value="">Todos los usuarios</option>@foreach ($usuarios as $u)<option value="{{ $u->id }}" @selected(request('usuario') == $u->id)>{{ $u->name }}</option>@endforeach</select>
        <select name="accion" class="campo w-auto"><option value="">Toda acción</option>@foreach ($acciones as $a)<option value="{{ $a }}" @selected(request('accion') === $a)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option>@endforeach</select>
    </x-filtros>
</div>
<div class="tarjeta overflow-hidden">
    <div class="overflow-x-auto">
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Sobre</th><th>Detalle</th><th>IP</th></tr></thead>
        <tbody class="divide-y divide-carbon-50">
        @foreach ($registros as $r)
            @php $color = ['eliminar' => 'insignia-roja', 'anular' => 'insignia-roja', 'crear' => 'insignia-verde', 'login' => 'insignia-gris', 'aprobar' => 'insignia-azul', 'completar' => 'insignia-verde'][$r->accion] ?? 'insignia-gris'; @endphp
            <tr>
                <td class="whitespace-nowrap text-xs tabular-nums">{{ $r->created_at->format('d/m/Y H:i:s') }}</td>
                <td class="whitespace-nowrap">{{ $r->user?->name ?? 'Sistema' }}</td>
                <td><span class="{{ $color }}">{{ ucfirst(str_replace('_', ' ', $r->accion)) }}</span></td>
                <td class="whitespace-nowrap text-xs text-carbon-500">{{ $r->entidad }}{{ $r->entidad_id ? ' #'.$r->entidad_id : '' }}</td>
                <td class="max-w-md text-sm">{{ $r->descripcion }}@if ($r->datos)<details class="text-xs text-carbon-500"><summary class="cursor-pointer">Datos</summary><pre class="mt-1 overflow-x-auto rounded bg-carbon-50 p-2">{{ json_encode($r->datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</td>
                <td class="font-mono text-xs text-carbon-400">{{ $r->ip }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    {{ $registros->links() }}
</div>
</x-layouts.app>
