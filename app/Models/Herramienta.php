<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Herramienta extends Model
{
    protected $fillable = [
        'codigo', 'nombre', 'categoria', 'marca', 'modelo', 'serie', 'estado', 'asignada_a',
        'costo', 'fecha_compra', 'foto', 'notas',
    ];

    protected $casts = ['fecha_compra' => 'date'];

    public const ESTADOS = [
        'disponible' => 'Disponible',
        'asignada' => 'Asignada',
        'en_reparacion' => 'En reparación',
        'perdida' => 'Perdida',
        'baja' => 'De baja',
    ];

    public const CONDICIONES = ['bueno' => 'Bueno', 'danado' => 'Dañado', 'perdido' => 'Perdido'];

    public function asignadaA()
    {
        return $this->belongsTo(User::class, 'asignada_a');
    }

    public function asignaciones()
    {
        return $this->hasMany(HerramientaAsignacion::class)->latest('entregado_at');
    }
}
