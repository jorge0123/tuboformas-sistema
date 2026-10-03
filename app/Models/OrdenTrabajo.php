<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrdenTrabajo extends Model
{
    protected $table = 'ordenes_trabajo';

    protected $fillable = [
        'folio', 'titulo', 'descripcion', 'maquina_id', 'plan_id', 'tipo', 'especialidad_id',
        'prioridad', 'estado', 'responsable_id', 'solicitante_id', 'fecha_inicio',
        'fecha_vencimiento', 'completada_at', 'progreso', 'horas_trabajo', 'detuvo_maquina',
        'horas_paro', 'motivo_espera', 'checklist', 'trabajo_realizado', 'orden_kanban',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_vencimiento' => 'date',
        'completada_at' => 'datetime',
        'checklist' => 'array',
        'detuvo_maquina' => 'boolean',
    ];

    public const TIPOS = [
        'preventivo' => 'Preventivo',
        'correctivo' => 'Correctivo',
        'predictivo' => 'Predictivo',
        'mejora' => 'Mejora',
        'ampliacion' => 'Ampliación',
        'proyecto' => 'Proyecto / instalación',
    ];

    public const PRIORIDADES = [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'critica' => 'Crítica',
    ];

    /** Columnas del Kanban, en orden. */
    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_progreso' => 'En progreso',
        'en_espera' => 'En espera',
        'completada' => 'Completada',
        'cancelada' => 'Cancelada',
    ];

    public const ABIERTOS = ['pendiente', 'en_progreso', 'en_espera'];

    public const SITUACIONES = [
        'atrasada' => 'Atrasada',
        'por_vencer' => 'Por vencer',
        'en_tiempo' => 'En tiempo',
        'sin_fecha' => 'Sin fecha',
        'completada_a_tiempo' => 'A tiempo',
        'completada_tarde' => 'Con retraso',
        'cancelada' => 'Cancelada',
    ];

    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
    }

    public function plan()
    {
        return $this->belongsTo(PlanMantenimiento::class, 'plan_id');
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function ayudantes()
    {
        return $this->belongsToMany(User::class, 'orden_trabajo_ayudantes');
    }

    public function seguimientos()
    {
        return $this->hasMany(OtSeguimiento::class)->latest('id');
    }

    public function archivos()
    {
        return $this->morphMany(Archivo::class, 'adjuntable')->latest();
    }

    public function bitacora()
    {
        return $this->hasOne(Bitacora::class);
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class);
    }

    public function tramos()
    {
        return $this->hasMany(OtTramo::class, 'orden_trabajo_id')->orderBy('inicio_at');
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estado, self::ABIERTOS, true);
    }

    /** Situación calculada, igual que la columna "Situación" del Excel. */
    public function situacion(): string
    {
        if ($this->estado === 'cancelada') {
            return 'cancelada';
        }
        if ($this->estado === 'completada') {
            return $this->fecha_vencimiento && $this->completada_at
                && $this->completada_at->toDateString() > $this->fecha_vencimiento->toDateString()
                ? 'completada_tarde' : 'completada_a_tiempo';
        }
        if (! $this->fecha_vencimiento) {
            return 'sin_fecha';
        }
        $hoy = today();
        if ($this->fecha_vencimiento->lt($hoy)) {
            return 'atrasada';
        }

        return $this->fecha_vencimiento->lte($hoy->copy()->addDays(2)) ? 'por_vencer' : 'en_tiempo';
    }

    /** OT que el usuario puede ver según ot.ver_todas / ot.ver_propias. */
    public function scopeVisiblesPara(Builder $q, User $u): Builder
    {
        if ($u->can('ot.ver_todas')) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('responsable_id', $u->id)
            ->orWhere('solicitante_id', $u->id)
            ->orWhereHas('ayudantes', fn ($a) => $a->where('users.id', $u->id)));
    }

    public function esDe(User $u): bool
    {
        return $this->responsable_id === $u->id
            || $this->solicitante_id === $u->id
            || $this->ayudantes->contains('id', $u->id);
    }

    /** Responsable o ayudante: quien ejecuta el trabajo. */
    public function laEjecuta(User $u): bool
    {
        return $this->responsable_id === $u->id || $this->ayudantes->contains('id', $u->id);
    }

    public function scopeEnSituacion(Builder $q, ?string $s): Builder
    {
        $hoy = today()->toDateString();
        $limite = today()->addDays(2)->toDateString();

        return match ($s) {
            'atrasada' => $q->whereIn('estado', self::ABIERTOS)->whereDate('fecha_vencimiento', '<', $hoy),
            'por_vencer' => $q->whereIn('estado', self::ABIERTOS)->whereBetween('fecha_vencimiento', [$hoy, $limite]),
            'en_tiempo' => $q->whereIn('estado', self::ABIERTOS)->whereDate('fecha_vencimiento', '>', $limite),
            'sin_fecha' => $q->whereIn('estado', self::ABIERTOS)->whereNull('fecha_vencimiento'),
            default => $q,
        };
    }
}
