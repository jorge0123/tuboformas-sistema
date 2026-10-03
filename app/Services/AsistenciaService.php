<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Auditoria;
use App\Models\OrdenTrabajo;
use App\Models\OtTramo;
use App\Models\User;
use App\Notifications\Aviso;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asistencia y tiempo laboral de las OT (docs/DISENO.md §2.10):
 * - quien tiene turno marca entrada y salida, y solo registra trabajo en OT estando marcado;
 * - el tiempo de una OT es la suma de sus tramos; un tramo corre mientras la persona trabaja
 *   en esa OT y se detiene al marcar salida, pausar, pasar a otra OT o dejar la OT en espera;
 * - al marcar entrada se reanuda la OT que quedó corriendo cuando marcó salida;
 * - si no marca salida, el sistema la cierra a la hora en que termina su turno.
 */
class AsistenciaService
{
    /** Margen después del fin del turno antes de cerrar una asistencia olvidada. */
    private const MARGEN_OLVIDO = 120;

    public function marcarEntrada(User $u): Asistencia
    {
        $this->cerrarOlvidadas($u);
        if ($abierta = $u->asistenciaAbierta()) {
            throw ValidationException::withMessages(['asistencia' => 'Ya marcaste tu entrada a las '.$abierta->entrada_at->format('H:i').'.']);
        }

        $asistencia = DB::transaction(function () use ($u) {
            $anterior = $u->asistencias()->whereNotNull('salida_at')->latest('salida_at')->first();
            $asistencia = $u->asistencias()->create([
                'fecha' => today(), 'entrada_at' => now(), 'turno_id' => $u->turno_id,
            ]);
            // La OT que quedó corriendo al marcar salida sigue donde se quedó.
            $pendiente = $anterior ? OtTramo::with('orden')->where('user_id', $u->id)->where('cierre', 'salida')
                ->where('fin_at', $anterior->salida_at)->latest('fin_at')->first() : null;
            if ($pendiente && $pendiente->orden->estado === 'en_progreso') {
                OtTramo::create(['orden_trabajo_id' => $pendiente->orden_trabajo_id, 'user_id' => $u->id, 'inicio_at' => now()]);
            }

            return $asistencia;
        });
        Auditoria::registrar('entrada', $asistencia, $u->name.' · '.$asistencia->entrada_at->format('d/m/Y H:i'));

        return $asistencia;
    }

    public function marcarSalida(User $u): Asistencia
    {
        $asistencia = $u->asistenciaAbierta();
        if (! $asistencia) {
            throw ValidationException::withMessages(['asistencia' => 'No has marcado tu entrada.']);
        }
        DB::transaction(function () use ($u, $asistencia) {
            $asistencia->update(['salida_at' => now()]);
            $this->cerrarTramosDe($u, $asistencia->salida_at, 'salida');
        });
        Auditoria::registrar('salida', $asistencia, $u->name.' · '.$asistencia->salida_at->format('d/m/Y H:i'));

        return $asistencia;
    }

    /**
     * Cierra las asistencias que quedaron abiertas después de terminar el turno (más un margen).
     * Se cierran a la hora de fin del turno y se avisa a la persona para que pida corrección si trabajó más.
     */
    public function cerrarOlvidadas(?User $u = null): int
    {
        $abiertas = Asistencia::with(['turno', 'user'])->abiertas()->when($u, fn ($q) => $q->where('user_id', $u->id))->get();
        $cerradas = 0;
        foreach ($abiertas as $a) {
            $fin = $this->finEsperado($a);
            if ($fin->copy()->addMinutes(self::MARGEN_OLVIDO)->isFuture()) {
                continue;
            }
            DB::transaction(function () use ($a, $fin) {
                $a->update(['salida_at' => $fin, 'salida_automatica' => true]);
                $this->cerrarTramosDe($a->user, $fin, 'salida');
            });
            $a->user->notify(new Aviso('Salida sin marcar',
                'No marcaste salida el '.$a->fecha->format('d/m/Y').'. Se cerró a las '.$fin->format('H:i').'; si trabajaste más, pide la corrección.',
                route('asistencia.index')));
            $cerradas++;
        }

        return $cerradas;
    }

    /** Corrige las horas de una asistencia. El tiempo de la OT que se cortó con esa salida se ajusta igual. */
    public function corregir(Asistencia $a, Carbon $entrada, ?Carbon $salida, ?string $nota, User $quien): void
    {
        if ($salida && $salida->lte($entrada)) {
            throw ValidationException::withMessages(['salida_at' => 'La salida debe ser después de la entrada.']);
        }
        DB::transaction(function () use ($a, $entrada, $salida, $nota, $quien) {
            $salidaAntes = $a->salida_at;
            if ($salidaAntes && $salida && ! $salidaAntes->equalTo($salida)) {
                $tramos = OtTramo::where('user_id', $a->user_id)->where('cierre', 'salida')->where('fin_at', $salidaAntes)->get();
                foreach ($tramos as $t) {
                    $fin = $salida->max($t->inicio_at);
                    $delta = round(($t->inicio_at->diffInMinutes($fin) - $t->inicio_at->diffInMinutes($t->fin_at)) / 60, 2);
                    $t->update(['fin_at' => $fin]);
                    $this->sumarHoras($t->orden_trabajo_id, $delta);
                }
            }
            $a->update([
                'fecha' => $entrada->toDateString(), 'entrada_at' => $entrada, 'salida_at' => $salida,
                'salida_automatica' => $salida ? false : $a->salida_automatica,
                'notas' => $nota, 'corregida_por' => $quien->id,
            ]);
        });
        Auditoria::registrar('corregir', $a, $a->user->name.' · '.$a->fecha->format('d/m/Y').($nota ? ": $nota" : ''));
    }

    // ── Tiempo en OT ───────────────────────────────────────────────────

    /** Exige que quien tiene turno esté marcado antes de registrar trabajo. */
    public function exigirEntrada(User $u): void
    {
        if ($u->marcaAsistencia() && ! $u->asistenciaAbierta()) {
            throw ValidationException::withMessages(['asistencia' => 'Marca tu entrada para registrar trabajo en las órdenes.']);
        }
    }

    /** Empieza a contar el tiempo de esta persona en la OT. Una persona trabaja en una sola OT a la vez. */
    public function iniciarTrabajo(OrdenTrabajo $ot, User $u): ?OtTramo
    {
        if (! $u->marcaAsistencia() || ! $u->asistenciaAbierta() || ! $ot->estaAbierta()) {
            return null;
        }
        $actual = OtTramo::abiertos()->where('user_id', $u->id)->first();
        if ($actual && $actual->orden_trabajo_id === $ot->id) {
            return $actual;
        }

        return DB::transaction(function () use ($ot, $u, $actual) {
            if ($actual) {
                $this->cerrar($actual, now(), 'otra_ot');
            }

            return OtTramo::create(['orden_trabajo_id' => $ot->id, 'user_id' => $u->id, 'inicio_at' => now()]);
        });
    }

    public function pausarTrabajo(OrdenTrabajo $ot, User $u): void
    {
        OtTramo::abiertos()->where('orden_trabajo_id', $ot->id)->where('user_id', $u->id)->get()
            ->each(fn ($t) => $this->cerrar($t, now(), 'pausa'));
    }

    /** Detiene a todos los que trabajan en la OT (en espera, completada o cancelada). */
    public function detenerOrden(OrdenTrabajo $ot, string $cierre): void
    {
        OtTramo::abiertos()->where('orden_trabajo_id', $ot->id)->get()->each(fn ($t) => $this->cerrar($t, now(), $cierre));
    }

    public function tramoAbierto(User $u): ?OtTramo
    {
        return OtTramo::with('orden')->abiertos()->where('user_id', $u->id)->first();
    }

    private function cerrarTramosDe(User $u, Carbon $fin, string $cierre): void
    {
        OtTramo::abiertos()->where('user_id', $u->id)->get()->each(fn ($t) => $this->cerrar($t, $fin, $cierre));
    }

    /** Cierra el tramo y suma su tiempo a las horas de la OT (las usa la bitácora y los reportes). */
    private function cerrar(OtTramo $t, Carbon $fin, string $cierre): void
    {
        $fin = $fin->max($t->inicio_at);
        $t->update(['fin_at' => $fin, 'cierre' => $cierre]);
        $this->sumarHoras($t->orden_trabajo_id, $t->horas());
    }

    private function sumarHoras(int $otId, float $horas): void
    {
        if ($horas != 0) {
            OrdenTrabajo::whereKey($otId)->update(['horas_trabajo' => DB::raw('greatest(coalesce(horas_trabajo, 0) + '.$horas.', 0)')]);
        }
    }

    /** Cuándo debía terminar: fin del turno ese día; en un día sin turno, lo que dura el turno (u 8 h). */
    private function finEsperado(Asistencia $a): Carbon
    {
        $t = $a->turno;
        if ($t && $t->trabajaEl($a->fecha)) {
            $fin = $t->finEl($a->fecha);
            if ($fin->gt($a->entrada_at)) {
                return $fin;
            }
        }

        return $a->entrada_at->copy()->addMinutes((int) round(($t?->horas() ?: 8) * 60));
    }
}
