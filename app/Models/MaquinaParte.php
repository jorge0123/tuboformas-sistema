<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaquinaParte extends Model
{
    protected $fillable = ['maquina_id', 'grupo', 'especificacion', 'dimensiones', 'cantidad', 'producto_id'];

    protected $casts = ['cantidad' => 'decimal:2'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
