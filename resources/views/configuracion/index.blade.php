<x-layouts.app titulo="Configuración">
@php $reportes = \App\Support\ReporteProgramado::REPORTES; @endphp

<div class="mb-6 inline-flex rounded-xl bg-carbon-100 p-1">
    @foreach (['reportes' => ['Reportes programados', 'calendario'], 'correo' => ['Correo', 'correo']] as $k => [$t, $i])
        <a href="{{ route('configuracion.index', ['tab' => $k]) }}" class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition {{ $tab === $k ? 'bg-white text-carbon-900 shadow-sm' : 'text-carbon-500 hover:text-carbon-800' }}"><x-icono :n="$i" clase="size-4" /> {{ $t }}</a>
    @endforeach
</div>

@if ($tab === 'reportes')
    <p class="mb-5 max-w-3xl text-sm text-carbon-600">Reportes que llegan solos a la hora indicada, por correo y a la campana del sistema. Por ejemplo, uno a las <b>8:00</b> con lo de ayer y la agenda del día, y otro a las <b>19:00</b> con el cierre del día.
        @unless ($correo['servidor'])<a href="{{ route('configuracion.index', ['tab' => 'correo']) }}" class="font-semibold text-marca-700 hover:underline">Configura el correo</a> para que también lleguen por correo; mientras tanto solo llegan a la campana.@endunless
    </p>

    <div class="space-y-4">
        @foreach ($envios as $e)
            @php $dest = count($e->usuarios ?? []) + count($e->correosExtra()); @endphp
            <section class="tarjeta overflow-hidden {{ $e->activo ? '' : 'opacity-70' }}" x-data="{ editando: false }">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center" x-show="!editando">
                    <div class="grid w-20 shrink-0 place-items-center rounded-xl bg-carbon-900 py-2 text-white">
                        <span class="font-display text-xl font-extrabold tabular-nums">{{ substr($e->hora, 0, 5) }}</span>
                        <span class="text-[10px] text-carbon-300">{{ $e->textoDias() === 'Lunes a sábado' ? 'Lun a sáb' : ($e->textoDias() === 'Lunes a viernes' ? 'Lun a vie' : $e->textoDias()) }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-display text-lg font-bold">{{ $e->nombre }}</p>
                            @unless ($e->activo)<span class="insignia-gris">Pausado</span>@endunless
                            <span class="insignia-gris">{{ \App\Models\EnvioProgramado::PERIODOS[$e->periodo] }}</span>
                        </div>
                        <p class="mt-1 text-sm text-carbon-600">{{ collect($e->reportes)->map(fn ($r) => $reportes[$r][0] ?? $r)->join(' · ') }}</p>
                        <p class="mt-1 text-xs text-carbon-500">
                            @if ($dest) {{ $dest }} {{ $dest === 1 ? 'destinatario' : 'destinatarios' }} @else <span class="font-semibold text-amber-700">Sin destinatarios: no se envía</span> @endif
                            · {{ collect([$e->por_correo ? 'correo' : null, $e->en_campana ? 'campana' : null])->filter()->join(' y ') ?: 'ningún canal' }}
                            · {{ $e->ultimo_envio_at ? 'último envío '.$e->ultimo_envio_at->diffForHumans() : 'todavía no se ha enviado' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('configuracion.envios.vista', $e) }}" target="_blank" class="btn-secundario btn-sm"><x-icono n="ojo" clase="size-4" /> Ver cómo llega</a>
                        <form method="POST" action="{{ route('configuracion.envios.enviar', $e) }}" data-confirmar="Se envía ahora a todos sus destinatarios." data-titulo="Enviar ahora" data-boton="Enviar">@csrf
                            <button class="btn-secundario btn-sm"><x-icono n="enviar" clase="size-4" /> Enviar ahora</button>
                        </form>
                        <button class="btn-secundario btn-sm" @click="editando = true"><x-icono n="lapiz" clase="size-4" /> Editar</button>
                        <form method="POST" action="{{ route('configuracion.envios.destroy', $e) }}" data-confirmar="Ya no se enviará. Los reportes que ya llegaron se conservan." data-titulo="Eliminar reporte programado" data-boton="Eliminar" data-peligro>@csrf @method('DELETE')
                            <button class="btn-fantasma btn-icono text-carbon-400 hover:text-marca-700" aria-label="Eliminar"><x-icono n="basura" clase="size-4" /></button>
                        </form>
                    </div>
                </div>
                <div x-show="editando" x-cloak>
                    <div class="tarjeta-cabeza"><h3 class="tarjeta-titulo">Editar «{{ $e->nombre }}»</h3></div>
                    @include('configuracion._envio', ['e' => $e])
                </div>
            </section>
        @endforeach

        <section class="tarjeta" x-data="{ abierto: {{ $envios->isEmpty() ? 'true' : 'false' }} }">
            <button type="button" class="flex w-full items-center gap-3 p-5 text-left" @click="abierto = !abierto">
                <span class="grid size-10 place-items-center rounded-xl bg-marca-50 text-marca-600"><x-icono n="mas" clase="size-5" /></span>
                <span class="font-display font-bold">Nuevo reporte programado</span>
                <x-icono n="abajo" clase="ml-auto size-4 text-carbon-400 transition" x-bind:class="abierto && 'rotate-180'" />
            </button>
            <div x-show="abierto" x-collapse class="border-t border-carbon-100">
                @include('configuracion._envio', ['e' => new \App\Models\EnvioProgramado])
            </div>
        </section>
    </div>

    <section class="tarjeta mt-8 overflow-hidden">
        <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Últimos envíos</h3><p class="text-xs text-carbon-500">Cada reporte queda guardado tal como se envió</p></div></div>
        @if ($historial->isEmpty())
            <x-vacio icono="historial" titulo="Todavía no se ha enviado ningún reporte" />
        @else
        <div class="divide-y divide-carbon-50">
            @foreach ($historial as $h)
                <a href="{{ route('reportes.enviado', $h) }}" target="_blank" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm transition hover:bg-carbon-50">
                    <x-icono n="archivo" clase="size-4 text-carbon-400" />
                    <span class="min-w-0 flex-1 truncate font-medium">{{ $h->titulo }}</span>
                    <span class="text-xs text-carbon-500">{{ count($h->destinatarios ?? []) }} en campana · {{ $h->correos_enviados }} {{ $h->correos_enviados === 1 ? 'correo' : 'correos' }}</span>
                    @if ($h->error)<span class="insignia-roja" title="{{ $h->error }}">Error de correo</span>@endif
                </a>
            @endforeach
        </div>
        @endif
    </section>
@else
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="tarjeta xl:col-span-2">
            <div class="tarjeta-cabeza"><div><h3 class="tarjeta-titulo">Correo de salida</h3><p class="text-xs text-carbon-500">Desde esta cuenta se envían los reportes programados y los avisos por correo.</p></div>
                @if ($correo['servidor'])<span class="insignia-verde">Configurado</span>@else<span class="insignia-gris">Sin configurar</span>@endif</div>
            <form method="POST" action="{{ route('configuracion.correo') }}" class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-6">@csrf @method('PUT')
                <div class="sm:col-span-4"><label class="etiqueta">Servidor SMTP</label><input name="servidor" value="{{ $correo['servidor'] }}" class="campo" placeholder="smtp.gmail.com"></div>
                <div class="sm:col-span-2"><label class="etiqueta">Puerto</label><input type="number" name="puerto" value="{{ $correo['puerto'] }}" class="campo" placeholder="587"></div>
                <div class="sm:col-span-3"><label class="etiqueta">Usuario (correo)</label><input name="usuario" value="{{ $correo['usuario'] }}" class="campo" autocomplete="off" placeholder="mantenimiento.tuboformas@gmail.com"></div>
                <div class="sm:col-span-3"><label class="etiqueta">Contraseña</label><input type="password" name="clave" class="campo" autocomplete="new-password" placeholder="{{ $correo['tiene_clave'] ? '•••••••• (guardada; escribe para cambiarla)' : 'Contraseña de aplicación' }}"></div>
                <div class="sm:col-span-3"><label class="etiqueta">Remitente <span class="font-normal text-carbon-500">(opcional)</span></label><input type="email" name="remitente" value="{{ $correo['remitente'] }}" class="campo" placeholder="Igual que el usuario"></div>
                <div class="sm:col-span-3"><label class="etiqueta">Nombre que se ve</label><input name="nombre" value="{{ $correo['nombre'] }}" class="campo"></div>
                <div class="flex justify-end sm:col-span-6"><button class="btn-primario"><x-icono n="check" clase="size-4" /> Guardar</button></div>
            </form>
            <form method="POST" action="{{ route('configuracion.correo.prueba') }}" class="flex flex-wrap items-end gap-2 border-t border-carbon-100 bg-carbon-50/60 p-5">@csrf
                <div class="min-w-56 flex-1"><label class="etiqueta">Enviar una prueba a</label><input type="email" name="para" value="{{ auth()->user()->email }}" required class="campo"></div>
                <button class="btn-oscuro"><x-icono n="enviar" clase="size-4" /> Enviar prueba</button>
            </form>
        </section>

        <aside class="space-y-4 text-sm">
            <div class="tarjeta p-5">
                <p class="font-display font-bold">Con Gmail</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-carbon-600">
                    <li>Crea una cuenta solo para el sistema (ej. mantenimiento.tuboformas@gmail.com).</li>
                    <li>Activa la verificación en dos pasos en esa cuenta.</li>
                    <li>En <i>Cuenta de Google → Seguridad → Contraseñas de aplicaciones</i> crea una para "Tuboformas".</li>
                    <li>Aquí: servidor <b>smtp.gmail.com</b>, puerto <b>587</b>, el correo y esa contraseña de 16 letras.</li>
                </ol>
            </div>
            <div class="tarjeta p-5">
                <p class="font-display font-bold">Con Outlook / Microsoft 365</p>
                <p class="mt-2 text-carbon-600">Servidor <b>smtp.office365.com</b>, puerto <b>587</b>. La cuenta debe tener habilitado "SMTP autenticado" (lo activa quien administra el correo).</p>
            </div>
            <p class="px-1 text-xs text-carbon-500">La PC servidor necesita internet para enviar correos. Los enlaces de los correos abren el sistema solo desde la red de la planta.</p>
        </aside>
    </div>
@endif
</x-layouts.app>
