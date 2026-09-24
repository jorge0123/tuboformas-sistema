<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Numeración correlativa sin huecos por concurrencia (bloqueo de fila). */
class Folios
{
    public static function siguiente(string $clave, int $digitos = 6): string
    {
        return DB::transaction(function () use ($clave, $digitos) {
            DB::table('folios')->insertOrIgnore(['clave' => $clave, 'ultimo' => 0]);
            $fila = DB::table('folios')->where('clave', $clave)->lockForUpdate()->first();
            $n = $fila->ultimo + 1;
            DB::table('folios')->where('clave', $clave)->update(['ultimo' => $n]);

            return $clave.'-'.str_pad((string) $n, $digitos, '0', STR_PAD_LEFT);
        });
    }
}
