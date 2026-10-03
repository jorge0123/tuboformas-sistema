{{-- Formulario de un reporte programado ($e nuevo o existente). --}}
@php
    $nuevo = ! $e->exists;
    $seleccionados = array_map('intval', $e->usuarios ?? []);
@endphp
<form method="POST" action="{{ $nuevo ? route('configuracion.envios.store') : route('configuracion.envios.update', $e) }}" class="space-y-5 p-5"
      x-data="{ buscar: '', marcados: @js($seleccionados) }">
    @csrf
    @unless ($nuevo) @method('PUT') @endunless

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
        <div class="sm:col-span-3"><label class="etiqueta">Nombre</label><input name="nombre" value="{{ $e->nombre }}" required class="campo" placeholder="Arranque del día"></div>
        <div class="sm:col-span-1"><label class="etiqueta">Hora</label><input type="time" name="hora" value="{{ $e->hora ? substr($e->hora, 0, 5) : '08:00' }}" required class="campo"></div>
        <div class="sm:col-span-2"><label class="etiqueta">Qué período cubre</label>
            <select name="periodo" class="campo">@foreach (\App\Models\EnvioProgramado::PERIODOS as $k => $v)<option value="{{ $k }}" @selected(($e->periodo ?? 'hoy') === $k)>{{ $v }}</option>@endforeach</select>
        </div>
    </div>

    <div>
        <span class="etiqueta">Días</span>
        <div class="flex flex-wrap gap-1.5">
            @foreach (\App\Models\Turno::DIAS as $n => $d)
                <label class="cursor-pointer"><input type="checkbox" name="dias[]" value="{{ $n }}" class="peer sr-only" @checked(in_array($n, array_map('intval', $e->dias ?? [1, 2, 3, 4, 5, 6])))>
                    <span class="block rounded-lg px-2.5 py-1.5 text-xs font-semibold ring-1 ring-carbon-200 transition peer-checked:bg-carbon-900 peer-checked:text-white peer-checked:ring-carbon-900">{{ $d }}</span></label>
            @endforeach
        </div>
    </div>

    <div>
        <span class="etiqueta">Qué incluye</span>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach (\App\Support\ReporteProgramado::REPORTES as $k => [$t, $ayuda])
                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl p-3 ring-1 ring-carbon-200 transition has-[:checked]:bg-marca-50/50 has-[:checked]:ring-marca-300">
                    <input type="checkbox" name="reportes[]" value="{{ $k }}" class="check mt-0.5" @checked(in_array($k, $e->reportes ?? ['resumen', 'atrasadas']))>
                    <span><span class="block text-sm font-semibold text-carbon-900">{{ $t }}</span><span class="block text-xs text-carbon-500">{{ $ayuda }}</span></span>
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <span class="etiqueta mb-0">Quién lo recibe <span class="font-normal text-carbon-500" x-text="'(' + marcados.length + ')'"></span></span>
            <input type="search" x-model="buscar" class="campo w-48 py-1.5 text-sm" placeholder="Buscar persona">
        </div>
        <div class="max-h-60 overflow-y-auto rounded-xl ring-1 ring-carbon-200">
            @foreach ($usuarios as $u)
                <label class="flex cursor-pointer items-center gap-3 border-b border-carbon-50 px-3 py-2 last:border-0 hover:bg-carbon-50"
                       x-show="!buscar || @js(mb_strtolower($u->name.' '.$u->puesto)).includes(buscar.toLowerCase())">
                    <input type="checkbox" name="usuarios[]" value="{{ $u->id }}" class="check" x-model.number="marcados">
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $u->name }}</span>
                        <span class="block truncate text-xs text-carbon-500">{{ $u->rolPrincipal()?->nombre }}{{ $u->puesto ? ' · '.$u->puesto : '' }}</span></span>
                    @if ($u->email)<span class="hidden truncate text-xs text-carbon-500 sm:block">{{ $u->email }}</span>@else<span class="insignia-gris">Sin correo · solo campana</span>@endif
                </label>
            @endforeach
        </div>
    </div>

    <div><label class="etiqueta">Otros correos <span class="font-normal text-carbon-500">(gerencia, dueños… separados por coma)</span></label>
        <input name="correos" value="{{ $e->correos }}" class="campo" placeholder="gerencia@tuboformas.com, dueño@gmail.com"></div>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl bg-carbon-50 p-3 text-sm">
        <span class="font-semibold text-carbon-700">Llega por:</span>
        <label class="flex items-center gap-2"><input type="checkbox" name="por_correo" value="1" class="check" @checked($e->por_correo ?? true)> Correo</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="en_campana" value="1" class="check" @checked($e->en_campana ?? true)> Campana del sistema</label>
        <label class="ml-auto flex items-center gap-2"><input type="checkbox" name="activo" value="1" class="check" @checked($e->activo ?? true)> Activo</label>
    </div>

    <div class="flex flex-wrap justify-end gap-2">
        @unless ($nuevo)<button type="button" class="btn-secundario" @click="editando = false">Cancelar</button>@endunless
        <button class="btn-primario"><x-icono n="check" clase="size-4" /> {{ $nuevo ? 'Crear reporte programado' : 'Guardar' }}</button>
    </div>
</form>
