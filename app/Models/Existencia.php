<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Existencia extends Model
{
    protected $fillable = ['producto_id', 'bodega_id', 'cantidad'];

    protected $casts = ['cantidad' => 'decimal:3'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }
}
