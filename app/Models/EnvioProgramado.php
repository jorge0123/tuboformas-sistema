<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** Reporte que se envía solo a una hora (ej. 08:00 arranque del día, 19:00 cierre). */
class EnvioProgramado extends Model
{
    protected $table = 'envios_programados';

    protected $fillable = ['nombre', 'hora', 'dias', 'periodo', 'reportes', 'usuarios', 'correos',
        'por_correo', 'en_campana', 'activo', 'ultimo_envio_at'];

    protected $casts = [
        'dias' => 'array', 'reportes' => 'array', 'usuarios' => 'array',
        'por_correo' => 'boolean', 'en_campana' => 'boolean', 'activo' => 'boolean',
        'ultimo_envio_at' => 'datetime',
    ];

    public const PERIODOS = ['hoy' => 'Lo de hoy', 'ayer' => 'Lo de ayer', 'semana' => 'Últimos 7 días'];

    /** Margen para enviar si la PC estuvo apagada a la hora exacta. */
    private const MARGEN_HORAS = 3;

    public function destinatarios()
    {
        return User::activos()->whereIn('id', $this->usuarios ?? [])->orderBy('name')->get();
    }

    /** Correos extra, limpios y sin repetir. */
    public function correosExtra(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $this->correos))
            ->filter(fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
    }

    public function horaHoy(): Carbon
    {
        return Carbon::parse(today()->toDateString().' '.$this->hora);
    }

    /** Le toca enviarse ahora: es su día, ya pasó su hora (hasta un margen) y hoy no se ha enviado. */
    public function tocaAhora(): bool
    {
        $hora = $this->horaHoy();

        return $this->activo
            && in_array(today()->isoWeekday(), array_map('intval', $this->dias ?? []), true)
            && now()->gte($hora) && now()->lt($hora->copy()->addHours(self::MARGEN_HORAS))
            && (! $this->ultimo_envio_at || $this->ultimo_envio_at->lt($hora));
    }

    /** [desde, hasta, texto] del período que cubre. */
    public function rango(): array
    {
        return match ($this->periodo) {
            'ayer' => [today()->subDay(), today()->subDay()->endOfDay(), 'ayer, '.today()->subDay()->translatedFormat('l j \d\e F')],
            'semana' => [today()->subDays(6), now(), 'del '.today()->subDays(6)->format('d/m').' al '.today()->format('d/m')],
            default => [today(), now(), 'hoy, '.today()->translatedFormat('l j \d\e F')],
        };
    }

    public function textoDias(): string
    {
        return (new Turno(['dias' => $this->dias]))->textoDias();
    }
}
