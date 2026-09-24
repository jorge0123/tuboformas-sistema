<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConteoLinea extends Model
{
    public $timestamps = false;

    protected $fillable = ['conteo_id', 'producto_id', 'cantidad_sistema', 'cantidad_contada', 'contado_por', 'contado_at'];

    protected $casts = [
        'cantidad_sistema' => 'decimal:3',
        'cantidad_contada' => 'decimal:3',
        'contado_at' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function diferencia(): ?float
    {
        return $this->cantidad_contada === null ? null
            : round((float) $this->cantidad_contada - (float) $this->cantidad_sistema, 3);
    }
}
