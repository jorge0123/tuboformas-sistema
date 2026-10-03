<?php

namespace App\Models;

use App\Support\Permisos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'puesto', 'telefono',
        'especialidad_id', 'turno_id', 'activo', 'notif_email', 'ultimo_acceso_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'notif_email' => 'boolean',
        ];
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    /** Asistencia abierta (marcó entrada y todavía no la salida). */
    public function asistenciaAbierta(): ?Asistencia
    {
        return $this->asistencias()->abiertas()->latest('entrada_at')->first();
    }

    /** Quien tiene turno marca entrada y salida, y no registra trabajo en OT fuera de turno. */
    public function marcaAsistencia(): bool
    {
        return $this->turno_id !== null;
    }

    public function herramientas()
    {
        return $this->hasMany(Herramienta::class, 'asignada_a');
    }

    public function esSuperAdmin(): bool
    {
        return $this->hasRole(Permisos::SUPER_ADMIN);
    }

    public function rolPrincipal(): ?Role
    {
        return $this->roles->first();
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }

    /** Personal que puede recibir OT: tiene ot.ejecutar por rol o directo. */
    public function scopeAsignables($q)
    {
        return $q->activos()->permission('ot.ejecutar')->orderBy('name');
    }
}
