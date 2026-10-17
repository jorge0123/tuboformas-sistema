<?php

use App\Models\User;
use App\Notifications\Aviso;
use App\Services\AsistenciaService;
use App\Services\OrdenTrabajoService;
use App\Services\ReporteProgramadoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('planes:generar', function (OrdenTrabajoService $ots) {
    $n = $ots->generarPreventivas();
    $this->info("Órdenes preventivas creadas: $n");
})->purpose('Crea las OT de los planes preventivos que entraron en su ventana de anticipación');

Artisan::command('asistencia:cerrar', function (AsistenciaService $s) {
    $this->info('Asistencias cerradas: '.$s->cerrarOlvidadas());
})->purpose('Cierra la asistencia de quien no marcó salida, a la hora en que terminó su turno');

Artisan::command('reportes:enviar', function (ReporteProgramadoService $s) {
    $this->info('Reportes programados enviados: '.$s->enviarPendientes());
})->purpose('Envía los reportes programados cuya hora ya llegó (Administración → Configuración)');

Artisan::command('sistema:avisar {titulo} {mensaje}', function (string $titulo, string $mensaje) {
    $admins = User::activos()->permission('usuarios.gestionar')->get();
    Notification::send($admins, new Aviso($titulo, $mensaje, route('dashboard')));
    $this->info('Aviso enviado a '.$admins->count().' administradores.');
})->purpose('Aviso del servidor a los administradores (lo usa deploy/windows/vigilante.ps1)');

// En cPanel un solo cron cada minuto: php artisan schedule:run (ver docs/DESPLIEGUE.md)
Schedule::command('planes:generar')->dailyAt('05:30');
Schedule::command('asistencia:cerrar')->everyThirtyMinutes();
Schedule::command('reportes:enviar')->everyMinute()->withoutOverlapping();
// Envía los correos en cola sin necesitar un proceso permanente.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
