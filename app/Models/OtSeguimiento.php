<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtSeguimiento extends Model
{
    protected $table = 'ot_seguimientos';

    protected $fillable = ['orden_trabajo_id', 'user_id', 'tipo', 'texto', 'progreso', 'estado', 'horas'];

    public const TIPOS = [
        'comentario' => 'Comentario',
        'avance' => 'Avance',
        'estado' => 'Cambio de estado',
        'asignacion' => 'Asignación',
        'sistema' => 'Sistema',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function archivos()
    {
        return $this->morphMany(Archivo::class, 'adjuntable');
    }
}
