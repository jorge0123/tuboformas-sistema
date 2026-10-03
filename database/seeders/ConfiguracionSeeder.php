<?php

namespace Database\Seeders;

use App\Models\EnvioProgramado;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Los dos reportes programados de inicio: arranque (8:00, lo de ayer y la agenda) y cierre
 * (19:00, lo de hoy). Sin destinatarios no se envían: se eligen en Configuración.
 * Solo se crean si no hay ninguno.
 */
class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        if (EnvioProgramado::exists()) {
            return;
        }
        // En local, los de mantenimiento y gerencia de ejemplo ya los reciben.
        $usuarios = User::whereIn('username', ['gmantto', 'amantto', 'gerente'])->pluck('id')->all();

        EnvioProgramado::create([
            'nombre' => 'Arranque del día', 'hora' => '08:00', 'dias' => [1, 2, 3, 4, 5, 6], 'periodo' => 'ayer',
            'reportes' => ['resumen', 'agenda', 'atrasadas', 'asistencia', 'repuestos'], 'usuarios' => $usuarios,
        ]);
        EnvioProgramado::create([
            'nombre' => 'Cierre del día', 'hora' => '19:00', 'dias' => [1, 2, 3, 4, 5, 6], 'periodo' => 'hoy',
            'reportes' => ['resumen', 'completadas', 'atrasadas', 'asistencia'], 'usuarios' => $usuarios,
        ]);
    }
}
