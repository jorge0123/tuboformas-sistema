<?php

namespace Database\Seeders;

use App\Models\Asistencia;
use App\Models\OrdenTrabajo;
use App\Models\OtTramo;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Turnos y tres semanas de asistencia para ver la ocupación del personal:
 * marcas con llegadas tarde, horas extra, salidas sin marcar, faltas y tiempo en OT.
 * Hoy: unos ya entraron y están trabajando en una orden, otro no ha llegado.
 */
class DemoAsistenciaSeeder extends Seeder
{
    public function run(): void
    {
        if (Turno::exists()) {
            return;
        }
        mt_srand(2026);
        $turnos = [
            'A' => Turno::create(['nombre' => 'Turno A (mañana)', 'hora_entrada' => '06:00', 'hora_salida' => '14:00', 'dias' => [1, 2, 3, 4, 5, 6], 'tolerancia' => 10]),
            'B' => Turno::create(['nombre' => 'Turno B (tarde)', 'hora_entrada' => '14:00', 'hora_salida' => '22:00', 'dias' => [1, 2, 3, 4, 5, 6], 'tolerancia' => 10]),
            'ADM' => Turno::create(['nombre' => 'Administrativo', 'hora_entrada' => '07:00', 'hora_salida' => '16:00', 'dias' => [1, 2, 3, 4, 5], 'tolerancia' => 15]),
        ];
        Turno::create(['nombre' => 'Turno C (noche)', 'hora_entrada' => '22:00', 'hora_salida' => '06:00', 'dias' => [1, 2, 3, 4, 5], 'tolerancia' => 10]);

        $u = User::whereIn('username', ['kenneth', 'selvin', 'canche', 'miguel', 'djose', 'amantto'])->get()->keyBy('username');
        $asignacion = ['kenneth' => 'A', 'selvin' => 'A', 'canche' => 'A', 'miguel' => 'B', 'djose' => 'B', 'amantto' => 'ADM'];
        foreach ($asignacion as $user => $t) {
            $u[$user]?->update(['turno_id' => $turnos[$t]->id]);
        }

        // Historial: 21 días hacia atrás (sin hoy).
        foreach ($asignacion as $user => $t) {
            $persona = $u[$user] ?? null;
            if (! $persona) {
                continue;
            }
            $turno = $turnos[$t];
            for ($d = 21; $d >= 1; $d--) {
                $dia = today()->subDays($d);
                if (! $turno->trabajaEl($dia) || mt_rand(1, 100) <= 4) {
                    continue; // descanso o falta
                }
                $entrada = $turno->inicioEl($dia)->addMinutes(mt_rand(1, 100) <= 15 ? mt_rand(12, 40) : mt_rand(-12, 6));
                $olvido = mt_rand(1, 100) <= 5;
                $salida = $olvido ? $turno->finEl($dia) : $turno->finEl($dia)->addMinutes(mt_rand(1, 100) <= 25 ? mt_rand(30, 150) : mt_rand(-5, 15));
                $a = Asistencia::create(['user_id' => $persona->id, 'fecha' => $dia, 'entrada_at' => $entrada, 'salida_at' => $salida,
                    'salida_automatica' => $olvido, 'turno_id' => $turno->id]);
                $this->tramosDelDia($persona, $a);
            }
        }

        // Hoy: según la hora, los del turno que ya empezó están adentro; uno de ellos no llegó.
        foreach ($asignacion as $user => $t) {
            $persona = $u[$user] ?? null;
            $turno = $turnos[$t];
            if (! $persona || ! $turno->trabajaEl(today()) || $user === 'canche') {
                continue;
            }
            $inicio = $turno->inicioEl(today());
            if ($inicio->isFuture()) {
                continue;
            }
            $entrada = $inicio->copy()->addMinutes($user === 'selvin' ? 18 : mt_rand(-8, 4));
            if ($turno->finEl(today())->isPast()) {
                // Su turno de hoy ya terminó: jornada completa.
                $a = Asistencia::create(['user_id' => $persona->id, 'fecha' => today(), 'entrada_at' => $entrada,
                    'salida_at' => $turno->finEl(today())->addMinutes(mt_rand(0, 20)), 'turno_id' => $turno->id]);
                $this->tramosDelDia($persona, $a);

                continue;
            }
            Asistencia::create(['user_id' => $persona->id, 'fecha' => today(), 'entrada_at' => $entrada, 'turno_id' => $turno->id]);
            $ot = OrdenTrabajo::where('estado', 'en_progreso')->where(fn ($q) => $q->where('responsable_id', $persona->id)
                ->orWhereHas('ayudantes', fn ($a) => $a->where('users.id', $persona->id)))->first();
            if ($ot) {
                OtTramo::create(['orden_trabajo_id' => $ot->id, 'user_id' => $persona->id, 'inicio_at' => $entrada->copy()->addMinutes(25)]);
            }
        }
    }

    /**
     * Reparte la jornada entre las OT de esa persona: tramos de 1 a 3 h con huecos (traslados,
     * almuerzo, tiempo sin orden). Igual que AsistenciaService, cada tramo cerrado suma a la OT.
     */
    private function tramosDelDia(User $persona, Asistencia $a): void
    {
        $ots = OrdenTrabajo::where(fn ($q) => $q->where('responsable_id', $persona->id)
            ->orWhereHas('ayudantes', fn ($x) => $x->where('users.id', $persona->id)))
            ->whereIn('estado', ['en_progreso', 'en_espera', 'completada'])->pluck('id');
        if ($ots->isEmpty()) {
            return;
        }
        $cursor = $a->entrada_at->copy()->addMinutes(mt_rand(10, 40));
        while (true) {
            $fin = $cursor->copy()->addMinutes(mt_rand(50, 180));
            if ($fin->gt($a->salida_at)) {
                break;
            }
            $t = OtTramo::create(['orden_trabajo_id' => $ots->random(), 'user_id' => $persona->id,
                'inicio_at' => $cursor, 'fin_at' => $fin, 'cierre' => ['pausa', 'otra_ot', 'espera'][mt_rand(0, 2)]]);
            OrdenTrabajo::whereKey($t->orden_trabajo_id)->increment('horas_trabajo', $t->horas());
            $cursor = $fin->copy()->addMinutes(mt_rand(15, 75));
        }
    }
}
