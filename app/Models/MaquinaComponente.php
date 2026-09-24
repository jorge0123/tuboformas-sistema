<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaquinaComponente extends Model
{
    protected $fillable = ['maquina_id', 'nombre', 'especificaciones', 'orden'];

    protected $casts = ['especificaciones' => 'array'];

    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
    }
}
