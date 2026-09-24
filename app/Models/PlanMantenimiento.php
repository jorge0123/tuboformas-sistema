<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PlanMantenimiento extends Model
{
    protected $table = 'planes_mantenimiento';

    protected $fillable = [
        'maquina_id', 'titulo', 'descripcion', 'checklist', 'tipo', 'especialidad_id',
        'responsable_id', 'prioridad', 'frecuencia_valor', 'frecuencia_unidad',
        'proxima_fecha', 'dias_anticipacion', 'duracion_estimada', 'activo',
    ];

    protected $casts = [
        'checklist' => 'array',
        'proxima_fecha' => 'date',
        'activo' => 'boolean',
    ];

    public const UNIDADES = ['dias' => 'días', 'semanas' => 'semanas', 'meses' => 'meses'];

    private const SINGULAR = ['dias' => 'día', 'semanas' => 'semana', 'meses' => 'mes'];

    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function ordenes()
    {
        return $this->hasMany(OrdenTrabajo::class, 'plan_id');
    }

    public function frecuenciaTexto(): string
    {
        return $this->frecuencia_valor == 1
            ? 'Cada '.(self::SINGULAR[$this->frecuencia_unidad] ?? $this->frecuencia_unidad)
            : "Cada {$this->frecuencia_valor} ".(self::UNIDADES[$this->frecuencia_unidad] ?? $this->frecuencia_unidad);
    }

    public function siguienteFecha(Carbon $desde): Carbon
    {
        $d = $desde->copy();

        return match ($this->frecuencia_unidad) {
            'dias' => $d->addDays($this->frecuencia_valor),
            'semanas' => $d->addWeeks($this->frecuencia_valor),
            default => $d->addMonthsNoOverflow($this->frecuencia_valor),
        };
    }
}
