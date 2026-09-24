<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'accion', 'entidad', 'entidad_id', 'descripcion', 'datos', 'ip'];

    protected $casts = ['datos' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Registra una acción del usuario actual. */
    public static function registrar(string $accion, ?Model $modelo = null, ?string $descripcion = null, ?array $datos = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'entidad' => $modelo ? class_basename($modelo) : null,
            'entidad_id' => $modelo?->getKey(),
            'descripcion' => $descripcion,
            'datos' => $datos,
            'ip' => request()?->ip(),
        ]);
    }
}
