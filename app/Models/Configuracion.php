<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Valores que se editan desde Administración → Configuración (clave/valor). */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    /** Claves que se guardan cifradas. */
    private const SECRETAS = ['correo_clave'];

    private static ?array $cache = null;

    public static function valor(string $clave, $defecto = null)
    {
        self::$cache ??= self::pluck('valor', 'clave')->all();
        $v = self::$cache[$clave] ?? null;
        if ($v !== null && in_array($clave, self::SECRETAS, true)) {
            try {
                $v = Crypt::decryptString($v);
            } catch (\Throwable) {
                $v = null;
            }
        }

        return $v ?? $defecto;
    }

    public static function guardar(array $valores): void
    {
        foreach ($valores as $clave => $v) {
            if (in_array($clave, self::SECRETAS, true) && $v !== null && $v !== '') {
                $v = Crypt::encryptString($v);
            }
            self::updateOrCreate(['clave' => $clave], ['valor' => $v === '' ? null : $v]);
        }
        self::$cache = null;
    }

    /** Aplica el correo guardado sobre la configuración de Laravel (si hay servidor configurado). */
    public static function aplicarCorreo(): void
    {
        $host = self::valor('correo_servidor');
        if (! $host) {
            return;
        }
        $puerto = (int) self::valor('correo_puerto', 587);
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $puerto,
            // 465 = SSL directo; 587 = STARTTLS (lo negocia solo).
            'mail.mailers.smtp.scheme' => $puerto === 465 ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => self::valor('correo_usuario'),
            'mail.mailers.smtp.password' => self::valor('correo_clave'),
            'mail.from.address' => self::valor('correo_remitente') ?: self::valor('correo_usuario'),
            'mail.from.name' => self::valor('correo_nombre', 'Sistema Tuboformas'),
        ]);
    }
}
