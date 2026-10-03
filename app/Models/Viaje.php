<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un camión con su piloto que sale a entregar uno o varios pedidos (paradas en orden). */
class Viaje extends Model
{
    protected $fillable = ['folio', 'vehiculo_id', 'piloto_id', 'estado', 'salida_at', 'regreso_at', 'notas', 'user_id'];

    protected $casts = ['salida_at' => 'datetime', 'regreso_at' => 'datetime'];

    public const ESTADOS = ['en_ruta' => 'En ruta', 'terminado' => 'Terminado'];

    public function vehiculo()
    {
        return $this->belongsTo(Maquina::class, 'vehiculo_id');
    }

    public function piloto()
    {
        return $this->belongsTo(User::class, 'piloto_id');
    }

    public function despachador()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Paradas en orden. Incluye las que regresaron (quedan en el historial del viaje). */
    public function pedidos()
    {
        return $this->hasMany(Pedido::class)->orderBy('orden_parada');
    }

    public function pendientes()
    {
        return $this->pedidos()->where('estado', 'en_ruta');
    }
}
