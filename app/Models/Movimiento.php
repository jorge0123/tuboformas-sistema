<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $fillable = [
        'folio', 'tipo', 'efecto', 'estado', 'fecha', 'bodega_origen_id', 'bodega_destino_id',
        'proveedor_id', 'maquina_id', 'orden_trabajo_id', 'movimiento_origen_id', 'documento',
        'referencia', 'notas', 'user_id', 'aprobado_por', 'aprobado_at', 'motivo_rechazo',
    ];

    protected $casts = ['fecha' => 'date', 'aprobado_at' => 'datetime'];

    /**
     * Tipos de movimiento, en el orden del flujo de la planta.
     * efecto: entrada | salida | traslado. aprobacion: queda pendiente si quien
     * lo registra no tiene movimientos.aprobar.
     */
    public const TIPOS = [
        'entrada_compra' => ['nombre' => 'Recepción de compra', 'efecto' => 'entrada',
            'ayuda' => 'Materia prima, repuestos o insumos que llegan de un proveedor.'],
        'salida_produccion' => ['nombre' => 'Salida a producción', 'efecto' => 'salida',
            'ayuda' => 'Tubos o materia prima que se entregan a una máquina u operador.'],
        'ingreso_produccion' => ['nombre' => 'Ingreso de producto terminado', 'efecto' => 'entrada',
            'ayuda' => 'Producto contado y empacado en las mesas que entra a bodega.'],
        'devolucion_produccion' => ['nombre' => 'Devolución de producción', 'efecto' => 'entrada',
            'ayuda' => 'Material que producción no usó y regresa a bodega.'],
        'salida_despacho' => ['nombre' => 'Despacho', 'efecto' => 'salida',
            'ayuda' => 'Producto terminado que sale hacia un cliente o sucursal.'],
        'consumo_mantenimiento' => ['nombre' => 'Consumo en mantenimiento', 'efecto' => 'salida',
            'ayuda' => 'Repuestos usados en una orden de trabajo.'],
        'traslado' => ['nombre' => 'Traslado entre bodegas', 'efecto' => 'traslado',
            'ayuda' => 'Mueve existencia de una bodega a otra.'],
        'ajuste_entrada' => ['nombre' => 'Ajuste de entrada', 'efecto' => 'entrada', 'aprobacion' => true,
            'ayuda' => 'Corrige faltantes del sistema. Requiere aprobación.'],
        'ajuste_salida' => ['nombre' => 'Ajuste de salida', 'efecto' => 'salida', 'aprobacion' => true,
            'ayuda' => 'Merma, daño o sobrante del sistema. Requiere aprobación.'],
        'reverso' => ['nombre' => 'Reverso por anulación', 'efecto' => null, 'interno' => true],
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente de aprobación',
        'confirmado' => 'Confirmado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
    ];

    public function lineas()
    {
        return $this->hasMany(MovimientoLinea::class);
    }

    public function bodegaOrigen()
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino()
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
    }

    public function orden()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_trabajo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function origen()
    {
        return $this->belongsTo(Movimiento::class, 'movimiento_origen_id');
    }

    public function reverso()
    {
        return $this->hasOne(Movimiento::class, 'movimiento_origen_id');
    }

    public function archivos()
    {
        return $this->morphMany(Archivo::class, 'adjuntable')->latest();
    }

    public function nombreTipo(): string
    {
        return self::TIPOS[$this->tipo]['nombre'] ?? $this->tipo;
    }

    public static function tiposCapturables(): array
    {
        return array_filter(self::TIPOS, fn ($t) => empty($t['interno']));
    }
}
