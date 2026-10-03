{{-- Reporte programado. Estilos en línea: los clientes de correo ignoran las hojas de estilo. --}}
@php
    $f = fn ($h) => \App\Support\Formato::duracion($h);
    $url = rtrim(config('app.url'), '/');
    $th = 'text-align:left;padding:8px 10px;font-size:11px;font-weight:600;color:#7d766f;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #e2ded9;';
    $td = 'padding:8px 10px;font-size:13px;color:#1f1d1b;border-bottom:1px solid #efece8;vertical-align:top;';
    $num = 'text-align:right;white-space:nowrap;';
    $tituloSeccion = 'margin:0 0 4px;font-size:16px;font-weight:700;color:#1f1d1b;font-family:Montserrat,Arial,sans-serif;';
    $notaSeccion = 'margin:0 0 12px;font-size:12px;color:#7d766f;';
    $vacio = 'margin:0;padding:12px;border-radius:8px;background:#f7f5f2;font-size:13px;color:#47423e;';
    $s = $secciones;
@endphp
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $envio->nombre }} · Tuboformas</title></head>
<body style="margin:0;padding:0;background:#f2efeb;font-family:Inter,Segoe UI,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2efeb;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;">

    {{-- Encabezado --}}
    <tr><td style="background:#1f1d1b;border-radius:14px 14px 0 0;padding:20px 24px;">
        <table role="presentation" width="100%"><tr>
            <td style="vertical-align:middle;"><span style="display:inline-block;background:#d90016;color:#fff;font-weight:800;font-size:15px;padding:6px 9px;border-radius:6px;font-family:Montserrat,Arial,sans-serif;">tf</span>
                <span style="color:#fff;font-weight:700;font-size:15px;margin-left:8px;font-family:Montserrat,Arial,sans-serif;">Tuboformas · Mantenimiento</span></td>
            <td style="text-align:right;color:#a39c94;font-size:12px;">{{ $generado->format('d/m/Y H:i') }}</td>
        </tr></table>
        <p style="margin:16px 0 0;color:#fff;font-size:22px;font-weight:800;font-family:Montserrat,Arial,sans-serif;">{{ $envio->nombre }}</p>
        <p style="margin:4px 0 0;color:#c9c3bc;font-size:13px;">Período: {{ $periodo }}</p>
    </td></tr>

    <tr><td style="background:#ffffff;border-radius:0 0 14px 14px;padding:8px 24px 24px;">

    @isset($s['resumen'])
        @php $r = $s['resumen']; @endphp
        <div style="padding-top:20px;">
            <p style="{{ $tituloSeccion }}">Resumen de mantenimiento</p>
            <p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#47423e;">
                Se crearon <b>{{ $r['creadas'] }}</b> y se completaron <b>{{ $r['completadas'] }}</b> órdenes{{ $r['a_tiempo'] !== null ? ', '.$r['a_tiempo'].' % a tiempo' : '' }}.
                Hoy quedan <b>{{ $r['abiertas'] }} abiertas</b>@if ($r['atrasadas']), <b style="color:#b8000f;">{{ $r['atrasadas'] }} atrasadas</b>@endif{{ $r['en_espera'] ? ', '.$r['en_espera'].' en espera' : '' }}{{ $r['sin_asignar'] ? ' y '.$r['sin_asignar'].' sin técnico asignado' : '' }}.
            </p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="6"><tr>
                @foreach ([['Completadas', $r['completadas']], ['A tiempo', $r['a_tiempo'] !== null ? $r['a_tiempo'].'%' : '—'], ['Horas de trabajo', \App\Support\Formato::numero($r['horas'], 1).' h'], ['Paro de máquinas', \App\Support\Formato::numero($r['paro'], 1).' h']] as [$t, $v])
                    <td width="25%" style="background:#f7f5f2;border-radius:10px;padding:12px;">
                        <p style="margin:0;font-size:11px;color:#7d766f;">{{ $t }}</p>
                        <p style="margin:2px 0 0;font-size:20px;font-weight:800;color:#1f1d1b;font-family:Montserrat,Arial,sans-serif;">{{ $v }}</p>
                    </td>
                @endforeach
            </tr></table>
        </div>
    @endisset

    @isset($s['agenda'])
        @php $a = $s['agenda']; @endphp
        <div style="padding-top:24px;">
            <p style="{{ $tituloSeccion }}">Agenda de hoy</p>
            <p style="{{ $notaSeccion }}">{{ $a['en_progreso'] }} órdenes en progreso · {{ $a['vencen']->count() }} vencen hoy · {{ $a['preventivos']->count() }} preventivos programados</p>
            @if ($a['vencen']->isEmpty() && $a['preventivos']->isEmpty())
                <p style="{{ $vacio }}">No hay órdenes que venzan hoy ni preventivos programados.</p>
            @else
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $th }}">Qué</th><th style="{{ $th }}">Máquina</th><th style="{{ $th }}">Responsable</th></tr>
                @foreach ($a['vencen'] as $o)
                    <tr><td style="{{ $td }}"><a href="{{ $url }}/ot/{{ $o->id }}" style="color:#1f1d1b;font-weight:600;text-decoration:none;">{{ $o->folio }} · {{ $o->titulo }}</a>{!! $o->prioridad === 'critica' ? ' <span style="color:#b8000f;font-size:11px;font-weight:700;">CRÍTICA</span>' : '' !!}<br><span style="font-size:11px;color:#7d766f;">Vence hoy · {{ $o->progreso }}%</span></td>
                        <td style="{{ $td }}">{{ $o->maquina?->etiqueta() ?? 'General' }}</td><td style="{{ $td }}">{{ $o->responsable?->name ?? 'Sin asignar' }}</td></tr>
                @endforeach
                @foreach ($a['preventivos'] as $p)
                    <tr><td style="{{ $td }}"><b>{{ $p->titulo }}</b><br><span style="font-size:11px;color:#7d766f;">Preventivo programado</span></td>
                        <td style="{{ $td }}">{{ $p->maquina?->etiqueta() ?? 'General' }}</td><td style="{{ $td }}">{{ $p->responsable?->name ?? 'Sin asignar' }}</td></tr>
                @endforeach
            </table>
            @endif
        </div>
    @endisset

    @isset($s['atrasadas'])
        @php $at = $s['atrasadas']; @endphp
        <div style="padding-top:24px;">
            <p style="{{ $tituloSeccion }}">Órdenes atrasadas <span style="color:#b8000f;">({{ $at['total'] }})</span></p>
            <p style="{{ $notaSeccion }}">Pasaron su fecha de vencimiento sin completarse.</p>
            @if ($at['ordenes']->isEmpty())
                <p style="{{ $vacio }}">Ninguna orden atrasada.</p>
            @else
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $th }}">Orden</th><th style="{{ $th }}">Responsable</th><th style="{{ $th }}{{ $num }}">Atraso</th><th style="{{ $th }}{{ $num }}">Avance</th></tr>
                @foreach ($at['ordenes'] as $o)
                    <tr><td style="{{ $td }}"><a href="{{ $url }}/ot/{{ $o->id }}" style="color:#1f1d1b;font-weight:600;text-decoration:none;">{{ $o->folio }} · {{ $o->titulo }}</a><br><span style="font-size:11px;color:#7d766f;">{{ $o->maquina?->etiqueta() ?? 'General' }}</span></td>
                        <td style="{{ $td }}">{{ $o->responsable?->name ?? 'Sin asignar' }}</td>
                        <td style="{{ $td }}{{ $num }}color:#b8000f;font-weight:600;">{{ (int) $o->fecha_vencimiento->diffInDays(today()) }} d</td>
                        <td style="{{ $td }}{{ $num }}">{{ $o->progreso }}%</td></tr>
                @endforeach
            </table>
            @endif
        </div>
    @endisset

    @isset($s['completadas'])
        @php $c = $s['completadas']['ordenes']; @endphp
        <div style="padding-top:24px;">
            <p style="{{ $tituloSeccion }}">Trabajo completado ({{ $c->count() }})</p>
            @if ($c->isEmpty())
                <p style="{{ $vacio }}">No se completaron órdenes en el período.</p>
            @else
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $th }}">Orden</th><th style="{{ $th }}">Técnico</th><th style="{{ $th }}{{ $num }}">Horas</th><th style="{{ $th }}">A tiempo</th></tr>
                @foreach ($c as $o)
                    <tr><td style="{{ $td }}"><a href="{{ $url }}/ot/{{ $o->id }}" style="color:#1f1d1b;font-weight:600;text-decoration:none;">{{ $o->folio }} · {{ $o->titulo }}</a><br><span style="font-size:11px;color:#7d766f;">{{ \App\Models\OrdenTrabajo::TIPOS[$o->tipo] }} · {{ $o->maquina?->etiqueta() ?? 'General' }}</span></td>
                        <td style="{{ $td }}">{{ $o->responsable?->name ?? '—' }}</td>
                        <td style="{{ $td }}{{ $num }}">{{ $f((float) $o->horas_trabajo) }}</td>
                        <td style="{{ $td }}">{!! $o->situacion() === 'completada_a_tiempo' ? '<span style="color:#0f7a54;font-weight:600;">Sí</span>' : '<span style="color:#b8000f;font-weight:600;">Tarde</span>' !!}</td></tr>
                @endforeach
            </table>
            @endif
        </div>
    @endisset

    @isset($s['asistencia'])
        @php $as = $s['asistencia']; @endphp
        <div style="padding-top:24px;">
            <p style="{{ $tituloSeccion }}">Asistencia y ocupación</p>
            <p style="{{ $notaSeccion }}">Ocupación del equipo: <b style="color:#1f1d1b;">{{ $as['ocupacion'] !== null ? $as['ocupacion'].' %' : '—' }}</b> (horas en órdenes / horas marcadas)</p>
            @if ($as['personas']->isEmpty())
                <p style="{{ $vacio }}">Nadie tiene turno asignado.</p>
            @else
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $th }}">Técnico</th><th style="{{ $th }}{{ $num }}">Marcado</th><th style="{{ $th }}{{ $num }}">En órdenes</th><th style="{{ $th }}{{ $num }}">Ocupación</th><th style="{{ $th }}">Observaciones</th></tr>
                @foreach ($as['personas'] as $p)
                    @php [$color, $texto] = \App\Support\Colores::ocupacion($p->ocupacion); @endphp
                    <tr><td style="{{ $td }}font-weight:600;">{{ $p->usuario->name }}</td>
                        <td style="{{ $td }}{{ $num }}">{{ $p->marcadas ? $f($p->marcadas) : '—' }}</td>
                        <td style="{{ $td }}{{ $num }}">{{ $p->en_ot ? $f($p->en_ot) : '—' }}</td>
                        <td style="{{ $td }}{{ $num }}"><b>{{ $p->ocupacion !== null ? $p->ocupacion.'%' : '—' }}</b> <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:{{ $color }};"></span> <span style="font-size:11px;color:#7d766f;">{{ $texto }}</span></td>
                        <td style="{{ $td }}font-size:12px;">{{ collect([
                            ! $p->marcadas && ! $p->faltas ? 'Sin marcas' : null,
                            $p->faltas ? $p->faltas.' '.($p->faltas === 1 ? 'falta' : 'faltas') : null,
                            $p->tardanzas ? $p->tardanzas.' tarde ('.$p->min_tarde.' min)' : null,
                            $p->salidas_antes ? $p->salidas_antes.' salió antes' : null,
                            $p->extra >= 0.25 ? \App\Support\Formato::numero($p->extra, 1).' h extra' : null,
                            $p->sin_salida ? $p->sin_salida.' sin marcar salida' : null,
                        ])->filter()->join(' · ') ?: '—' }}</td></tr>
                @endforeach
            </table>
            @endif
        </div>
    @endisset

    @isset($s['repuestos'])
        @php $rp = $s['repuestos']['productos']; @endphp
        <div style="padding-top:24px;">
            <p style="{{ $tituloSeccion }}">Repuestos por pedir ({{ $rp->count() }})</p>
            @if ($rp->isEmpty())
                <p style="{{ $vacio }}">Todos los repuestos están por encima del mínimo.</p>
            @else
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><th style="{{ $th }}">Repuesto</th><th style="{{ $th }}{{ $num }}">Hay</th><th style="{{ $th }}{{ $num }}">Mínimo</th></tr>
                @foreach ($rp as $p)
                    <tr><td style="{{ $td }}">{{ $p->codigo }} · {{ $p->nombre }}</td>
                        <td style="{{ $td }}{{ $num }}color:#b8000f;font-weight:600;">{{ \App\Support\Formato::numero($p->existencia) }} {{ $p->unidad->abreviatura }}</td>
                        <td style="{{ $td }}{{ $num }}">{{ \App\Support\Formato::numero($p->stock_minimo) }}</td></tr>
                @endforeach
            </table>
            @endif
        </div>
    @endisset

        <p style="margin:28px 0 0;text-align:center;"><a href="{{ $url }}" style="display:inline-block;background:#d90016;color:#fff;text-decoration:none;font-weight:600;font-size:14px;padding:10px 20px;border-radius:8px;">Abrir el sistema</a></p>
    </td></tr>

    <tr><td style="padding:16px 8px;text-align:center;font-size:11px;color:#a39c94;">
        Reporte automático «{{ $envio->nombre }}» · {{ substr($envio->hora, 0, 5) }} · {{ mb_strtolower($envio->textoDias()) }}.<br>
        Se configura en Administración → Configuración. Los enlaces abren solo dentro de la red de la planta.
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
