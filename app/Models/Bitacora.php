<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    protected $fillable = [
        'maquina_id', 'orden_trabajo_id', 'fecha', 'tipo', 'componente', 'trabajo_realizado',
        'horas', 'responsable_id', 'responsable_nombre', 'proveedor_id', 'garantia', 'costo',
        'horometro', 'comentarios', 'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'garantia' => 'boolean',
    ];

    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
    }

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function archivos()
    {
        return $this->morphMany(Archivo::class, 'adjuntable')->latest();
    }

    public function nombreResponsable(): string
    {
        return $this->responsable?->name ?? $this->responsable_nombre ?? '—';
    }
}
