<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoLinea extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'movimiento_id', 'producto_id', 'cantidad', 'costo_unitario', 'saldo', 'notas',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'costo_unitario' => 'decimal:4',
        'saldo' => 'decimal:3',
    ];

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
