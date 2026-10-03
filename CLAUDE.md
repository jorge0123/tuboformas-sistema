# Sistema Tuboformas

Sistema interno de mantenimiento (con bodega de repuestos) para Tuboformas Guatemala.
La lógica del negocio, los roles y las reglas están en `docs/DISENO.md`: léelo antes de cambiar algo.

## Stack
- Laravel 12 (PHP 8.2+), MySQL 8, Blade + Tailwind v4 + Alpine.js, spatie/laravel-permission.
- Se instala en una PC con Windows de la planta (Laragon, IP fija, servicios): `docs/INSTALACION-WINDOWS.md` y scripts en `deploy/windows/`. También puede ir a cPanel: `docs/DESPLIEGUE.md`.

## Local
- Laragon (MySQL root sin contraseña, base `tuboformas`).
- `php artisan serve --port=8090` (el 8000 lo usan otros proyectos). Correo: Mailpit en http://127.0.0.1:8025.
- Datos de ejemplo: `php artisan migrate:fresh --seed` (en local carga `DemoSeeder`). Usuarios de ejemplo: `admin`, `gmantto`, `amantto` (lleva la bodega de repuestos), `selvin` y `miguel` (técnicos con turno), `supervisor`, `contador`, `gerente`… contraseña `Tuboformas2026!`.
- Más volumen de datos (máquinas, fichas, repuestos, movimientos, OT): `php artisan db:seed --class=DemoAmpliadoSeeder` (ya se incluye en `migrate:fresh --seed` en local).

## Convenciones
- Todo en español: código de dominio (modelos, columnas, rutas), textos de interfaz y comentarios.
- Permisos: `App\Support\Permisos` es la fuente única (módulos, permisos y roles). Después de agregar uno, correr `php artisan db:seed --class=RolesPermisosSeeder`.
- La existencia de repuestos (`productos.existencia`) solo cambia en `App\Services\InventarioService`. Un movimiento confirmado no se edita: se anula con reverso.
- El ciclo de una OT (crear, seguimiento, completar → bitácora) vive en `App\Services\OrdenTrabajoService`.
- Asistencia y tiempo laboral de las OT (marcar entrada/salida, tramos por OT) viven en `App\Services\AsistenciaService`. Las horas de la OT se suman al cerrar cada tramo.
- Avisos: `App\Notifications\Aviso` (campana + correo si el usuario lo tiene activo). Reportes por hora: `App\Services\ReporteProgramadoService` (Administración → Configuración).
- El correo SMTP se configura desde el sistema (tabla `configuraciones`), no en el `.env` del servidor.
- UI: nada de emojis, solo íconos `<x-icono n="...">`. Confirmaciones con `data-confirmar` en el `<form>` (nunca `confirm()`), avisos con `avisar()` en JS o `->with('ok', ...)`.
- Clases de Tailwind siempre completas en el código (nada de `col-span-{{ $n }}`), o Tailwind no las genera.
