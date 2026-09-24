<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoPresentacion extends Model
{
    protected $table = 'producto_presentaciones';

    protected $fillable = ['producto_id', 'nombre', 'factor'];

    protected $casts = ['factor' => 'decimal:3'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
