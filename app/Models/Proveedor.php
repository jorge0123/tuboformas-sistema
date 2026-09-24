<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'nit', 'contacto', 'telefono', 'email', 'direccion', 'tipos', 'notas', 'activo'];

    protected $casts = ['tipos' => 'array', 'activo' => 'boolean'];

    public const TIPOS = [
        'repuestos' => 'Repuestos',
        'servicios' => 'Servicios técnicos',
        'materia_prima' => 'Materia prima',
        'insumos' => 'Insumos',
    ];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'proveedor_producto')
            ->withPivot(['id', 'codigo_proveedor', 'precio', 'dias_entrega'])->withTimestamps();
    }

    public function bitacoras()
    {
        return $this->hasMany(Bitacora::class);
    }
}
