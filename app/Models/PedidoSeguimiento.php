<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoSeguimiento extends Model
{
    protected $fillable = ['pedido_id', 'user_id', 'estado', 'texto'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
