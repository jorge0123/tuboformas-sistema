<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Copia de un reporte programado tal como se envió (se abre desde la campana o el historial). */
class ReporteEnviado extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'reportes_enviados';

    protected $fillable = ['envio_programado_id', 'titulo', 'destinatarios', 'correos_enviados', 'error', 'html'];

    protected $casts = ['destinatarios' => 'array'];

    public function envio()
    {
        return $this->belongsTo(EnvioProgramado::class, 'envio_programado_id');
    }

    public function puedeVer(User $u): bool
    {
        return $u->can('configuracion.gestionar') || in_array($u->id, $this->destinatarios ?? [], true);
    }
}
