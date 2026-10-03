<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = ['nombre', 'nit', 'contacto', 'telefono', 'email', 'direccion', 'municipio', 'notas', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    public function scopeBuscar($q, ?string $texto)
    {
        if ($texto) {
            $q->where(fn ($w) => $w->where('nombre', 'like', "%$texto%")->orWhere('nit', 'like', "%$texto%")
                ->orWhere('contacto', 'like', "%$texto%")->orWhere('municipio', 'like', "%$texto%"));
        }
    }
}
