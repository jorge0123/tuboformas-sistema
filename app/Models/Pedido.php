<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $fillable = [
        'folio', 'cliente_id', 'estado', 'prioridad', 'tipo_entrega', 'fecha_entrega', 'jornada', 'direccion_entrega',
        'contacto_nombre', 'contacto_telefono', 'orden_compra', 'condicion_pago', 'notas', 'bodega_id', 'vendedor_id',
        'preparado_por', 'despachado_por', 'documento', 'vehiculo', 'piloto', 'vehiculo_id', 'piloto_id', 'viaje_id', 'orden_parada', 'recibido_por', 'movimiento_id',
        'motivo_cancelacion', 'preparando_at', 'listo_at', 'en_ruta_at', 'entregado_at', 'cancelado_at',
    ];

    protected $casts = [
        'fecha_entrega' => 'date',
        'preparando_at' => 'datetime', 'listo_at' => 'datetime', 'en_ruta_at' => 'datetime',
        'entregado_at' => 'datetime', 'cancelado_at' => 'datetime',
    ];

    /** Flujo: nuevo → preparando → listo → en_ruta → entregado (quien recoge salta en_ruta). */
    public const ESTADOS = [
        'nuevo' => 'Nuevo',
        'preparando' => 'En preparación',
        'listo' => 'Listo para despacho',
        'en_ruta' => 'En ruta',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];

    public const ACTIVOS = ['nuevo', 'preparando', 'listo', 'en_ruta'];

    public const TIPOS_ENTREGA = [
        'ruta' => 'Con nuestro camión',
        'recoge' => 'El cliente recoge',
        'transporte' => 'Por paquetería',
    ];

    /** Lo que significa cada tipo, para quien ingresa el pedido. */
    public const AYUDA_ENTREGA = [
        'ruta' => 'Lo llevamos con un camión y piloto de la empresa.',
        'recoge' => 'El cliente pasa a la planta por su pedido.',
        'transporte' => 'Para clientes lejos: se envía por Cargo Expreso, Guatex u otra, con número de guía.',
    ];

    public const JORNADAS = ['manana' => 'Mañana', 'tarde' => 'Tarde'];

    public const CONDICIONES_PAGO = ['contado' => 'Contado', 'credito' => 'Crédito'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lineas()
    {
        return $this->hasMany(PedidoLinea::class)->orderBy('id');
    }

    public function seguimientos()
    {
        return $this->hasMany(PedidoSeguimiento::class)->latest('id');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function preparador()
    {
        return $this->belongsTo(User::class, 'preparado_por');
    }

    public function despachador()
    {
        return $this->belongsTo(User::class, 'despachado_por');
    }

    public function vehiculoAsignado()
    {
        return $this->belongsTo(Maquina::class, 'vehiculo_id');
    }

    public function pilotoAsignado()
    {
        return $this->belongsTo(User::class, 'piloto_id');
    }

    /** "CAM1 · Camión KIA" (flota propia) o la empresa de transporte escrita a mano. */
    public function nombreVehiculo(): ?string
    {
        return $this->vehiculoAsignado ? trim($this->vehiculoAsignado->etiqueta().' '.$this->vehiculoAsignado->marca) : $this->vehiculo;
    }

    public function nombrePiloto(): ?string
    {
        return $this->pilotoAsignado?->name ?? $this->piloto;
    }

    public function viaje()
    {
        return $this->belongsTo(Viaje::class);
    }

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class);
    }

    public function archivos()
    {
        return $this->morphMany(Archivo::class, 'adjuntable')->latest();
    }

    public function estaActivo(): bool
    {
        return in_array($this->estado, self::ACTIVOS, true);
    }

    /** Pasos que recorre este pedido (quien recoge no pasa por "en ruta"). */
    public function pasos(): array
    {
        $pasos = ['nuevo', 'preparando', 'listo', 'en_ruta', 'entregado'];

        return $this->tipo_entrega === 'recoge' ? array_values(array_diff($pasos, ['en_ruta'])) : $pasos;
    }

    /** Siguiente estado del flujo, o null si ya terminó. */
    public function siguiente(): ?string
    {
        $pasos = $this->pasos();
        $i = array_search($this->estado, $pasos, true);

        return $i === false ? null : ($pasos[$i + 1] ?? null);
    }

    public function atrasado(): bool
    {
        return $this->estaActivo() && $this->fecha_entrega->lt(today());
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->whereIn('estado', self::ACTIVOS);
    }

    /** Quien lo creó ve los suyos; quien tiene pedidos.ver_todos o prepara en bodega ve todos. */
    public function scopeVisiblesPara(Builder $q, User $u): Builder
    {
        // El piloto ve además las entregas que le tocan.
        return $u->canAny(['pedidos.ver_todos', 'pedidos.preparar']) ? $q
            : $q->where(fn ($w) => $w->where('vendedor_id', $u->id)->orWhere('piloto_id', $u->id));
    }

    public function progresoPreparacion(): array
    {
        $total = $this->lineas->count();

        return [$this->lineas->where('preparada', true)->count(), $total];
    }
}
