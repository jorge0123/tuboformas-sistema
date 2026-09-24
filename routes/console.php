<?php

use App\Services\OrdenTrabajoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('planes:generar', function (OrdenTrabajoService $ots) {
    $n = $ots->generarPreventivas();
    $this->info("Órdenes preventivas creadas: $n");
})->purpose('Crea las OT de los planes preventivos que entraron en su ventana de anticipación');

// En cPanel un solo cron cada minuto: php artisan schedule:run (ver docs/DESPLIEGUE.md)
Schedule::command('planes:generar')->dailyAt('05:30');
// Envía los correos en cola sin necesitar un proceso permanente.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
