<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Maquina extends Model
{
    protected $fillable = [
        'numero', 'codigo', 'nombre', 'area_id', 'marca', 'modelo', 'serie', 'anio',
        'ubicacion', 'criticidad', 'estado', 'horometro', 'observaciones', 'foto',
    ];

    public const ESTADOS = [
        'operativa' => 'Operativa',
        'en_mantenimiento' => 'En mantenimiento',
        'fuera_servicio' => 'Fuera de servicio',
        'baja' => 'De baja',
    ];

    public const CRITICIDADES = [
        'A' => 'A · Crítica (detiene producción)',
        'B' => 'B · Importante',
        'C' => 'C · Baja',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function componentes()
    {
        return $this->hasMany(MaquinaComponente::class)->orderBy('orden');
    }

    public function partes()
    {
        return $this->hasMany(MaquinaParte::class)->orderBy('grupo');
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class)->latest('fecha');
    }

    public function ordenes()
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    public function planes()
    {
        return $this->hasMany(PlanMantenimiento::class);
    }

    public function archivos()
    {
        return $this->morphMany(Archivo::class, 'adjuntable')->latest();
    }

    public function etiqueta(): string
    {
        return trim(($this->codigo ? $this->codigo.' · ' : '').$this->nombre);
    }

    public function scopeBuscar($q, ?string $texto)
    {
        if ($texto) {
            $q->where(fn ($w) => $w->where('nombre', 'like', "%$texto%")
                ->orWhere('codigo', 'like', "%$texto%")
                ->orWhere('marca', 'like', "%$texto%")
                ->orWhere('modelo', 'like', "%$texto%"));
        }
    }
}
