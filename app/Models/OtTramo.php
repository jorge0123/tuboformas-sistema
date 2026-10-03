<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tiempo de trabajo efectivo de una persona en una OT (solo corre mientras está marcada). */
class OtTramo extends Model
{
    protected $fillable = ['orden_trabajo_id', 'user_id', 'inicio_at', 'fin_at', 'cierre'];

    protected $casts = ['inicio_at' => 'datetime', 'fin_at' => 'datetime'];

    public const CIERRES = [
        'pausa' => 'Pausó', 'salida' => 'Marcó salida', 'espera' => 'Orden en espera',
        'completada' => 'Orden completada', 'cancelada' => 'Orden cancelada', 'otra_ot' => 'Pasó a otra orden',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAbiertos($q)
    {
        $q->whereNull('fin_at');
    }

    public function horas(): float
    {
        return round($this->inicio_at->diffInMinutes($this->fin_at ?? now()) / 60, 2);
    }
}
