<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $fillable = [
        'folio', 'tipo', 'efecto', 'estado', 'fecha', 'proveedor_id', 'maquina_id', 'orden_trabajo_id', 'movimiento_origen_id', 'documento',
        'referencia', 'notas', 'user_id', 'aprobado_por', 'aprobado_at', 'motivo_rechazo',
    ];

    protected $casts = ['fecha' => 'date', 'aprobado_at' => 'datetime'];

    /**
     * Tipos de movimiento de la bodega de repuestos.
     * efecto: entrada | salida. aprobacion: queda pendiente si quien
     * lo registra no tiene movimientos.aprobar.
     */
    public const TIPOS = [
        'entrada_compra' => ['nombre' => 'Recepción de compra', 'efecto' => 'entrada',
            'ayuda' => 'Repuestos o insumos que llegan de un proveedor.'],
        'consumo_mantenimiento' => ['nombre' => 'Consumo en mantenimiento', 'efecto' => 'salida',
            'ayuda' => 'Repuestos usados en una orden de trabajo.'],
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
