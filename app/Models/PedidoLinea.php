<?php

namespace App\Models;

use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;

class PedidoLinea extends Model
{
    public $timestamps = false;

    protected $fillable = ['pedido_id', 'producto_id', 'presentacion_id', 'cantidad', 'factor', 'cantidad_base', 'preparada', 'cantidad_preparada', 'notas'];

    protected $casts = ['preparada' => 'boolean', 'cantidad' => 'decimal:3', 'cantidad_base' => 'decimal:3', 'cantidad_preparada' => 'decimal:3', 'factor' => 'decimal:3'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function presentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class, 'presentacion_id');
    }

    /** "12 Bolsa x 300" o "450 pza". */
    public function descripcionCantidad(): string
    {
        $n = Formato::numero($this->cantidad);

        return $this->presentacion ? "$n {$this->presentacion->nombre}" : "$n {$this->producto->unidad->abreviatura}";
    }

    /** Se armó menos de lo pedido. */
    public function incompleta(): bool
    {
        return $this->preparada && $this->cantidad_preparada !== null && (float) $this->cantidad_preparada < (float) $this->cantidad_base;
    }
}
