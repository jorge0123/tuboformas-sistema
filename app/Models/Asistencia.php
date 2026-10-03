<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $fillable = ['user_id', 'fecha', 'entrada_at', 'salida_at', 'salida_automatica', 'turno_id', 'notas', 'corregida_por'];

    protected $casts = [
        'fecha' => 'date',
        'entrada_at' => 'datetime',
        'salida_at' => 'datetime',
        'salida_automatica' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class);
    }

    public function corrector()
    {
        return $this->belongsTo(User::class, 'corregida_por');
    }

    public function scopeAbiertas($q)
    {
        $q->whereNull('salida_at');
    }

    /** Horas marcadas; si sigue abierta, hasta ahora. */
    public function horas(): float
    {
        return round($this->entrada_at->diffInMinutes($this->salida_at ?? now()) / 60, 2);
    }

    /** Minutos tarde contra su turno (0 si llegó a tiempo, dentro de la tolerancia o sin turno). */
    public function minutosTarde(): int
    {
        if (! $this->turno || ! $this->turno->trabajaEl($this->fecha)) {
            return 0;
        }
        $tarde = (int) $this->turno->inicioEl($this->fecha)->diffInMinutes($this->entrada_at, false);

        return $tarde > $this->turno->tolerancia ? $tarde : 0;
    }

    /** Horas por encima de lo que dura su turno ese día (todo cuenta como extra en un día que no le toca). */
    public function horasExtra(): float
    {
        if (! $this->turno || ! $this->salida_at) {
            return 0;
        }
        $base = $this->turno->trabajaEl($this->fecha) ? $this->turno->horas() : 0;

        return max(0, round($this->horas() - $base, 2));
    }
}
