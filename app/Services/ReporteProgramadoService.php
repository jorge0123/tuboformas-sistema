<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\EnvioProgramado;
use App\Models\ReporteEnviado;
use App\Notifications\Aviso;
use App\Support\ReporteProgramado;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Envía un reporte programado (docs/DISENO.md §2.11): por correo a cada destinatario y a la
 * campana de los usuarios elegidos. Guarda una copia para verlo después.
 */
class ReporteProgramadoService
{
    /** Envía los que ya tocan. Lo corre la tarea de cada minuto. */
    public function enviarPendientes(): int
    {
        $n = 0;
        foreach (EnvioProgramado::where('activo', true)->get() as $e) {
            if ($e->tocaAhora()) {
                $this->enviar($e);
                $n++;
            }
        }

        return $n;
    }

    public function html(EnvioProgramado $e): string
    {
        return view('correos.reporte', ReporteProgramado::armar($e))->render();
    }

    public function enviar(EnvioProgramado $e, bool $manual = false): ReporteEnviado
    {
        $usuarios = $e->destinatarios();
        $correos = $e->por_correo
            ? $usuarios->pluck('email')->filter()->merge($e->correosExtra())->map(fn ($c) => mb_strtolower(trim($c)))->unique()->values()
            : collect();
        if ($manual && $usuarios->isEmpty() && $correos->isEmpty()) {
            throw ValidationException::withMessages(['usuarios' => 'Agrega al menos un destinatario.']);
        }

        $datos = ReporteProgramado::armar($e);
        $html = view('correos.reporte', $datos)->render();
        $titulo = $e->nombre.' · '.now()->format('d/m/Y H:i');

        $enviados = 0;
        $error = null;
        if ($correos->isNotEmpty()) {
            Configuracion::aplicarCorreo();
            foreach ($correos as $correo) {
                try {
                    Mail::html($html, fn ($m) => $m->to($correo)->subject($e->nombre.' · Tuboformas · '.now()->format('d/m')));
                    $enviados++;
                } catch (\Throwable $ex) {
                    $error = mb_strimwidth('No se pudo enviar a '.$correo.': '.$ex->getMessage(), 0, 250, '…');
                    report($ex);
                }
            }
        }

        $registro = ReporteEnviado::create([
            'envio_programado_id' => $e->id, 'titulo' => $titulo, 'destinatarios' => $usuarios->pluck('id')->all(),
            'correos_enviados' => $enviados, 'error' => $error, 'html' => $html,
        ]);

        if ($e->en_campana && $usuarios->isNotEmpty()) {
            Notification::sendNow($usuarios, new Aviso($e->nombre, $this->resumen($datos), route('reportes.enviado', $registro), 'Ver reporte'), ['database']);
        }
        if (! $manual) {
            $e->update(['ultimo_envio_at' => now()]);
        }

        return $registro;
    }

    /** Una línea para la campana. */
    private function resumen(array $datos): string
    {
        $s = $datos['secciones'];
        $partes = [];
        if (isset($s['resumen'])) {
            $partes[] = $s['resumen']['completadas'].' completadas';
            $partes[] = $s['resumen']['atrasadas'].' atrasadas';
        } elseif (isset($s['atrasadas'])) {
            $partes[] = $s['atrasadas']['total'].' atrasadas';
        }
        if (isset($s['agenda'])) {
            $partes[] = $s['agenda']['vencen']->count().' vencen hoy';
        }
        if (isset($s['asistencia']) && $s['asistencia']['ocupacion'] !== null) {
            $partes[] = 'ocupación '.$s['asistencia']['ocupacion'].' %';
        }

        return ucfirst($datos['periodo']).($partes ? ': '.implode(' · ', $partes) : '').'.';
    }
}
