<?php

use App\Services\AsistenciaService;
use App\Services\OrdenTrabajoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('planes:generar', function (OrdenTrabajoService $ots) {
    $n = $ots->generarPreventivas();
    $this->info("Órdenes preventivas creadas: $n");
})->purpose('Crea las OT de los planes preventivos que entraron en su ventana de anticipación');

Artisan::command('asistencia:cerrar', function (AsistenciaService $s) {
    $this->info('Asistencias cerradas: '.$s->cerrarOlvidadas());
})->purpose('Cierra la asistencia de quien no marcó salida, a la hora en que terminó su turno');

// En cPanel un solo cron cada minuto: php artisan schedule:run (ver docs/DESPLIEGUE.md)
Schedule::command('planes:generar')->dailyAt('05:30');
Schedule::command('asistencia:cerrar')->everyThirtyMinutes();
// Envía los correos en cola sin necesitar un proceso permanente.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
