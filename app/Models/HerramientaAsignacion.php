<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HerramientaAsignacion extends Model
{
    public $timestamps = false;

    protected $table = 'herramienta_asignaciones';

    protected $fillable = [
        'herramienta_id', 'user_id', 'entregado_por', 'entregado_at', 'estado_entrega',
        'recibido_por', 'devuelto_at', 'estado_devolucion', 'notas',
    ];

    protected $casts = ['entregado_at' => 'datetime', 'devuelto_at' => 'datetime'];

    public function herramienta()
    {
        return $this->belongsTo(Herramienta::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entregadoPor()
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function recibidoPor()
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }
}
