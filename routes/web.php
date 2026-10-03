<?php

use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ConteoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HerramientaController;
use App\Http\Controllers\MaquinaController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OrdenTrabajoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');
});

Route::middleware(['auth', 'activo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password', [PerfilController::class, 'password'])->name('perfil.password');

    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('/notificaciones/recientes', [NotificacionController::class, 'recientes'])->name('notificaciones.recientes');
    Route::get('/notificaciones/{id}/abrir', [NotificacionController::class, 'abrir'])->name('notificaciones.abrir');
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');

    // Archivos adjuntos (fotos, manuales, evidencias) de cualquier entidad.
    Route::post('/archivos', [ArchivoController::class, 'store'])->name('archivos.store');
    Route::delete('/archivos/{archivo}', [ArchivoController::class, 'destroy'])->name('archivos.destroy');

    // ── Asistencia ─────────────────────────────────────────────────────
    Route::get('asistencia', [AsistenciaController::class, 'index'])->name('asistencia.index');
    Route::get('asistencia/exportar', [AsistenciaController::class, 'exportar'])->name('asistencia.exportar');
    Route::post('asistencia/entrada', [AsistenciaController::class, 'entrada'])->name('asistencia.entrada');
    Route::post('asistencia/salida', [AsistenciaController::class, 'salida'])->name('asistencia.salida');
    Route::put('asistencia/{asistencia}', [AsistenciaController::class, 'update'])->name('asistencia.update');
    Route::resource('turnos', TurnoController::class)->only(['index', 'store', 'update']);

    // ── Mantenimiento ──────────────────────────────────────────────────
    Route::get('maquinas/exportar', [MaquinaController::class, 'exportar'])->name('maquinas.exportar');
    Route::resource('maquinas', MaquinaController::class)->parameters(['maquinas' => 'maquina']);

    Route::get('ot/kanban', [OrdenTrabajoController::class, 'kanban'])->name('ot.kanban');
    Route::get('ot/exportar', [OrdenTrabajoController::class, 'exportar'])->name('ot.exportar');
    Route::post('ot/{ot}/seguimiento', [OrdenTrabajoController::class, 'seguimiento'])->name('ot.seguimiento');
    Route::post('ot/{ot}/comentario', [OrdenTrabajoController::class, 'comentario'])->name('ot.comentario');
    Route::post('ot/{ot}/checklist', [OrdenTrabajoController::class, 'checklist'])->name('ot.checklist');
    Route::post('ot/{ot}/completar', [OrdenTrabajoController::class, 'completar'])->name('ot.completar');
    Route::post('ot/{ot}/cancelar', [OrdenTrabajoController::class, 'cancelar'])->name('ot.cancelar');
    Route::post('ot/{ot}/mover', [OrdenTrabajoController::class, 'mover'])->name('ot.mover');
    Route::post('ot/{ot}/trabajar', [OrdenTrabajoController::class, 'trabajar'])->name('ot.trabajar');
    Route::post('ot/{ot}/pausar', [OrdenTrabajoController::class, 'pausar'])->name('ot.pausar');
    Route::post('ot/{ot}/repuestos', [OrdenTrabajoController::class, 'repuestos'])->name('ot.repuestos');
    Route::resource('ot', OrdenTrabajoController::class)->parameters(['ot' => 'ot']);

    Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario');

    Route::get('bitacora/exportar', [BitacoraController::class, 'exportar'])->name('bitacora.exportar');
    Route::resource('bitacora', BitacoraController::class)->except(['show'])->parameters(['bitacora' => 'bitacora']);

    Route::post('planes/generar', [PlanController::class, 'generar'])->name('planes.generar');
    Route::resource('planes', PlanController::class)->except(['show'])->parameters(['planes' => 'plan']);

    Route::get('herramientas/mias', [HerramientaController::class, 'mias'])->name('herramientas.mias');
    Route::post('herramientas/{herramienta}/asignar', [HerramientaController::class, 'asignar'])->name('herramientas.asignar');
    Route::post('herramientas/{herramienta}/devolver', [HerramientaController::class, 'devolver'])->name('herramientas.devolver');
    Route::resource('herramientas', HerramientaController::class)->parameters(['herramientas' => 'herramienta']);

    Route::post('proveedores/{proveedor}/productos', [ProveedorController::class, 'agregarProducto'])->name('proveedores.productos.store');
    Route::delete('proveedores/{proveedor}/productos/{producto}', [ProveedorController::class, 'quitarProducto'])->name('proveedores.productos.destroy');
    Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);

    // ── Bodega de repuestos ────────────────────────────────────────────
    Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
    Route::get('productos/exportar', [ProductoController::class, 'exportar'])->name('productos.exportar');
    Route::resource('productos', ProductoController::class)->parameters(['productos' => 'producto']);

    Route::get('movimientos/exportar', [MovimientoController::class, 'exportar'])->name('movimientos.exportar');
    Route::post('movimientos/{movimiento}/aprobar', [MovimientoController::class, 'aprobar'])->name('movimientos.aprobar');
    Route::post('movimientos/{movimiento}/rechazar', [MovimientoController::class, 'rechazar'])->name('movimientos.rechazar');
    Route::post('movimientos/{movimiento}/anular', [MovimientoController::class, 'anular'])->name('movimientos.anular');
    Route::resource('movimientos', MovimientoController::class)->only(['index', 'create', 'store', 'show']);

    Route::post('conteos/{conteo}/capturar', [ConteoController::class, 'capturar'])->name('conteos.capturar');
    Route::post('conteos/{conteo}/aplicar', [ConteoController::class, 'aplicar'])->name('conteos.aplicar');
    Route::post('conteos/{conteo}/cancelar', [ConteoController::class, 'cancelar'])->name('conteos.cancelar');
    Route::resource('conteos', ConteoController::class)->only(['index', 'create', 'store', 'show']);

    // ── Reportes ───────────────────────────────────────────────────────
    Route::get('reportes/mantenimiento', [ReporteController::class, 'mantenimiento'])->name('reportes.mantenimiento');
    Route::get('reportes/ocupacion', [ReporteController::class, 'ocupacion'])->name('reportes.ocupacion');
    Route::get('reportes/repuestos', [ReporteController::class, 'repuestos'])->name('reportes.repuestos');

    // ── Administración ─────────────────────────────────────────────────
    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])->parameters(['usuarios' => 'usuario']);
    Route::resource('roles', RolController::class)->except(['show'])->parameters(['roles' => 'rol']);
    Route::get('catalogos/{catalogo?}', [CatalogoController::class, 'index'])->name('catalogos.index');
    Route::post('catalogos/{catalogo}', [CatalogoController::class, 'store'])->name('catalogos.store');
    Route::put('catalogos/{catalogo}/{id}', [CatalogoController::class, 'update'])->name('catalogos.update');
    Route::get('auditoria', [UsuarioController::class, 'auditoria'])->name('auditoria');
});
