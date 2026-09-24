<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'codigo', 'nombre', 'tipo', 'categoria_id', 'medida', 'unidad_id', 'stock_minimo',
        'costo_promedio', 'ubicacion', 'foto', 'descripcion', 'activo',
    ];

    protected $casts = [
        'stock_minimo' => 'decimal:3',
        'costo_promedio' => 'decimal:4',
        'activo' => 'boolean',
    ];

    public const TIPOS = [
        'materia_prima' => 'Materia prima',
        'producto_terminado' => 'Producto terminado',
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

    public function presentaciones()
    {
        return $this->hasMany(ProductoPresentacion::class)->orderBy('factor');
    }

    public function existencias()
    {
        return $this->hasMany(Existencia::class);
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

    /** Existencia total en todas las bodegas (usa withSum si viene cargado). */
    public function stockTotal(): float
    {
        return (float) ($this->existencias_sum_cantidad ?? $this->existencias->sum('cantidad'));
    }

    public function bajoMinimo(): bool
    {
        return (float) $this->stock_minimo > 0 && $this->stockTotal() < (float) $this->stock_minimo;
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
