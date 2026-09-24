<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoLinea extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'movimiento_id', 'producto_id', 'presentacion_id', 'cantidad', 'factor', 'cantidad_base',
        'costo_unitario', 'saldo_origen', 'saldo_destino', 'notas',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'factor' => 'decimal:3',
        'cantidad_base' => 'decimal:3',
        'costo_unitario' => 'decimal:4',
        'saldo_origen' => 'decimal:3',
        'saldo_destino' => 'decimal:3',
    ];

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function presentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class);
    }
}
