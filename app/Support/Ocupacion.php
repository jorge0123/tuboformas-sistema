<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\OrdenTrabajo;
use App\Models\OtTramo;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Ocupación del personal en un período: horas marcadas (asistencia) contra horas trabajando
 * en órdenes (tramos). Ocupación = horas en OT / horas marcadas.
 */
class Ocupacion
{
    /** Por persona con turno (o con marcas en el período). */
    public static function porPersona(Carbon $desde, Carbon $hasta): Collection
    {
        $fin = $hasta->copy()->endOfDay();
        $asistencias = Asistencia::with('turno')->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->get()->groupBy('user_id');
        $tramos = OtTramo::whereBetween('inicio_at', [$desde, $fin])->get()->groupBy('user_id');
        $completadas = OrdenTrabajo::where('estado', 'completada')->whereBetween('completada_at', [$desde, $fin])
            ->selectRaw('responsable_id, count(*) n')->groupBy('responsable_id')->pluck('n', 'responsable_id');

        $ids = User::activos()->whereNotNull('turno_id')->pluck('id')->merge($asistencias->keys())->unique();

        return User::with(['turno', 'especialidad'])->whereIn('id', $ids)->orderBy('name')->get()->map(function ($u) use ($asistencias, $tramos, $completadas, $desde, $hasta) {
            $suyas = $asistencias[$u->id] ?? collect();
            $marcadas = $suyas->sum(fn ($a) => $a->horas());
            $enOt = ($tramos[$u->id] ?? collect())->sum(fn ($t) => $t->horas());

            return (object) [
                'usuario' => $u,
                'dias' => $suyas->pluck('fecha')->map->toDateString()->unique()->count(),
                'faltas' => self::faltas($u, $suyas, $desde, $hasta),
                'marcadas' => round($marcadas, 2),
                'en_ot' => round($enOt, 2),
                'ocupacion' => $marcadas > 0 ? (int) round(min(100, $enOt / $marcadas * 100)) : null,
                'tardanzas' => $suyas->filter(fn ($a) => $a->minutosTarde() > 0)->count(),
                'min_tarde' => $suyas->sum(fn ($a) => $a->minutosTarde()),
                'extra' => round($suyas->sum(fn ($a) => $a->horasExtra()), 2),
                'sin_salida' => $suyas->where('salida_automatica', true)->count(),
                'completadas' => (int) ($completadas[$u->id] ?? 0),
            ];
        });
    }

    /** Cada persona con turno: si ya entró, si llegó tarde, si falta y en qué OT está trabajando. */
    public static function hoy(): Collection
    {
        $usuarios = User::activos()->whereNotNull('turno_id')->with(['turno', 'especialidad'])->orderBy('name')->get();
        $asistencias = Asistencia::with('turno')->whereIn('user_id', $usuarios->pluck('id'))
            ->where(fn ($q) => $q->whereDate('fecha', today())->orWhereNull('salida_at'))->orderBy('entrada_at')->get()->groupBy('user_id');
        $tramos = OtTramo::with('orden')->abiertos()->whereIn('user_id', $usuarios->pluck('id'))->get()->keyBy('user_id');

        $tramosHoy = OtTramo::whereIn('user_id', $usuarios->pluck('id'))->where(fn ($q) => $q->where('inicio_at', '>=', today())->orWhereNull('fin_at'))
            ->get()->groupBy('user_id');

        return $usuarios->map(function ($u) use ($asistencias, $tramos, $tramosHoy) {
            $suyas = $asistencias[$u->id] ?? collect();
            $abierta = $suyas->firstWhere('salida_at', null);
            $primera = $suyas->first();
            $leToca = $u->turno->trabajaEl(today());
            $estado = match (true) {
                (bool) $abierta => 'en_turno',
                (bool) $primera => 'salio',
                $leToca && $u->turno->inicioEl(today())->addMinutes($u->turno->tolerancia)->isPast() => 'falta',
                $leToca => 'por_entrar',
                default => 'libre',
            };

            return (object) [
                'usuario' => $u, 'estado' => $estado, 'asistencia' => $abierta ?? $primera, 'tramo' => $tramos[$u->id] ?? null,
                'tarde' => $primera?->minutosTarde() ?? 0, 'horas' => $horas = $suyas->sum(fn ($a) => $a->horas()),
                'ocupacion' => $horas > 0 ? (int) round(min(100, ($tramosHoy[$u->id] ?? collect())->sum(fn ($t) => $t->horas()) / $horas * 100)) : null,
            ];
        })->sortBy(fn ($f) => array_search($f->estado, ['en_turno', 'falta', 'por_entrar', 'salio', 'libre']));
    }

    /** Horas marcadas y en OT de todo el personal, día por día. */
    public static function porDia(Carbon $desde, Carbon $hasta): Collection
    {
        $asistencias = Asistencia::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->get()->groupBy(fn ($a) => $a->fecha->toDateString());
        $tramos = OtTramo::whereBetween('inicio_at', [$desde, $hasta->copy()->endOfDay()])->get()->groupBy(fn ($t) => $t->inicio_at->toDateString());

        return collect(CarbonPeriod::create($desde, $hasta))->map(fn ($d) => (object) [
            'fecha' => $d->copy(),
            'marcadas' => round(($asistencias[$d->toDateString()] ?? collect())->sum(fn ($a) => $a->horas()), 1),
            'en_ot' => round(($tramos[$d->toDateString()] ?? collect())->sum(fn ($t) => $t->horas()), 1),
        ]);
    }

    /** Días que le tocaba según su turno (hasta ayer) en los que no marcó. */
    private static function faltas(User $u, Collection $suyas, Carbon $desde, Carbon $hasta): int
    {
        if (! $u->turno) {
            return 0;
        }
        $tope = $hasta->copy()->min(today()->subDay());
        $inicio = $desde->copy()->max($u->created_at->copy()->startOfDay());
        if ($inicio->gt($tope)) {
            return 0;
        }
        $marcados = $suyas->pluck('fecha')->map->toDateString()->flip();

        return collect(CarbonPeriod::create($inicio, $tope))
            ->filter(fn ($d) => $u->turno->trabajaEl($d) && ! isset($marcados[$d->toDateString()]))->count();
    }
}
