<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** Horario de trabajo. Un turno cuya salida es menor que la entrada termina al día siguiente. */
class Turno extends Model
{
    protected $fillable = ['nombre', 'hora_entrada', 'hora_salida', 'dias', 'tolerancia', 'activo'];

    protected $casts = ['dias' => 'array', 'activo' => 'boolean'];

    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function trabajaEl(CarbonInterface $dia): bool
    {
        return in_array($dia->isoWeekday(), array_map('intval', $this->dias ?? []), true);
    }

    public function inicioEl(CarbonInterface $dia): Carbon
    {
        return Carbon::parse($dia->toDateString().' '.$this->hora_entrada);
    }

    public function finEl(CarbonInterface $dia): Carbon
    {
        $fin = Carbon::parse($dia->toDateString().' '.$this->hora_salida);

        return $fin->lte($this->inicioEl($dia)) ? $fin->addDay() : $fin;
    }

    /** Horas que dura el turno. */
    public function horas(): float
    {
        $hoy = today();

        return round($this->inicioEl($hoy)->diffInMinutes($this->finEl($hoy)) / 60, 2);
    }

    public function horario(): string
    {
        return substr($this->hora_entrada, 0, 5).' a '.substr($this->hora_salida, 0, 5);
    }

    public function textoDias(): string
    {
        $d = array_map('intval', $this->dias ?? []);
        sort($d);

        return match ($d) {
            [1, 2, 3, 4, 5] => 'Lunes a viernes',
            [1, 2, 3, 4, 5, 6] => 'Lunes a sábado',
            [1, 2, 3, 4, 5, 6, 7] => 'Todos los días',
            default => implode(', ', array_map(fn ($n) => self::DIAS[$n], $d)),
        };
    }
}
