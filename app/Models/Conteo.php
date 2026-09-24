<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conteo extends Model
{
    protected $fillable = ['folio', 'bodega_id', 'estado', 'fecha', 'notas', 'user_id', 'aplicado_por', 'aplicado_at'];

    protected $casts = ['fecha' => 'date', 'aplicado_at' => 'datetime'];

    public const ESTADOS = ['abierto' => 'Abierto', 'aplicado' => 'Aplicado', 'cancelado' => 'Cancelado'];

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }

    public function lineas()
    {
        return $this->hasMany(ConteoLinea::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aplicadoPor()
    {
        return $this->belongsTo(User::class, 'aplicado_por');
    }
}
