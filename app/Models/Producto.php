<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'codigo', 'nombre', 'tipo', 'categoria_id', 'medida', 'unidad_id', 'existencia', 'stock_minimo',
        'costo_promedio', 'ubicacion', 'foto', 'descripcion', 'activo',
    ];

    protected $casts = [
        'existencia' => 'decimal:3',
        'stock_minimo' => 'decimal:3',
        'costo_promedio' => 'decimal:4',
        'activo' => 'boolean',
    ];

    public const TIPOS = [
        'repuesto' => 'Repuesto',
        'insumo' => 'Insumo',
    ];

    public function categoria()
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }

    public function proveedores()
    {
        return $this->belongsToMany(Proveedor::class, 'proveedor_producto')
            ->withPivot(['id', 'codigo_proveedor', 'precio', 'dias_entrega'])->withTimestamps();
    }

    public function lineas()
    {
        return $this->hasMany(MovimientoLinea::class);
    }

    public function archivos()
    {
        return $this->morphMany(Archivo::class, 'adjuntable')->latest();
    }

    public function bajoMinimo(): bool
    {
        return (float) $this->stock_minimo > 0 && (float) $this->existencia < (float) $this->stock_minimo;
    }

    public function scopeBajoMinimo($q)
    {
        $q->where('stock_minimo', '>', 0)->whereColumn('existencia', '<', 'stock_minimo');
    }

    public function etiqueta(): string
    {
        return $this->codigo.' · '.$this->nombre;
    }

    public function scopeBuscar($q, ?string $texto)
    {
        if ($texto) {
            $q->where(fn ($w) => $w->where('nombre', 'like', "%$texto%")
                ->orWhere('codigo', 'like', "%$texto%")
                ->orWhere('medida', 'like', "%$texto%"));
        }
    }
}
