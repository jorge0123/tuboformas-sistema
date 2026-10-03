<?php

namespace App\Support;

/**
 * Catálogo único de permisos y roles del sistema.
 *
 * Los permisos son "modulo.accion". El seeder (RolesPermisosSeeder) crea todo lo
 * que hay aquí; la pantalla de Roles muestra la matriz agrupada por módulo.
 * Para agregar un permiso: se agrega aquí, se vuelve a correr el seeder y se usa
 * con @can / $this->authorize / middleware 'can:'.
 */
class Permisos
{
    public const SUPER_ADMIN = 'super_admin';

    /** Módulo => [nombre, [permiso => descripción]] */
    public static function modulos(): array
    {
        return [
            'dashboard' => ['Tablero', [
                'dashboard.ver' => 'Ver el tablero de inicio',
            ]],
            'maquinas' => ['Máquinas', [
                'maquinas.ver' => 'Ver máquinas y fichas técnicas',
                'maquinas.crear' => 'Crear máquinas',
                'maquinas.editar' => 'Editar máquinas y fichas técnicas',
                'maquinas.eliminar' => 'Eliminar máquinas',
            ]],
            'ot' => ['Órdenes de trabajo', [
                'ot.ver_todas' => 'Ver todas las OT',
                'ot.ver_propias' => 'Ver sus OT (responsable, ayudante o solicitante)',
                'ot.crear' => 'Crear OT / reportar fallas',
                'ot.editar' => 'Editar cualquier OT',
                'ot.asignar' => 'Asignar responsables',
                'ot.ejecutar' => 'Registrar avance y completar sus OT',
                'ot.cancelar' => 'Cancelar OT',
                'ot.eliminar' => 'Eliminar OT',
            ]],
            'bitacora' => ['Bitácora de mantenimiento', [
                'bitacora.ver' => 'Ver la bitácora',
                'bitacora.crear' => 'Registrar trabajos en la bitácora',
                'bitacora.editar' => 'Editar registros de la bitácora',
                'bitacora.eliminar' => 'Eliminar registros de la bitácora',
            ]],
            'planes' => ['Planes preventivos', [
                'planes.ver' => 'Ver planes y calendario',
                'planes.gestionar' => 'Crear y editar planes preventivos',
            ]],
            'asistencia' => ['Asistencia y ocupación', [
                'asistencia.ver' => 'Ver la asistencia y la ocupación de todo el personal',
                'asistencia.gestionar' => 'Corregir marcas y administrar turnos',
            ]],
            'herramientas' => ['Herramientas', [
                'herramientas.ver' => 'Ver el catálogo de herramientas',
                'herramientas.gestionar' => 'Crear y editar herramientas',
                'herramientas.asignar' => 'Asignar y recibir herramientas',
            ]],
            'proveedores' => ['Proveedores', [
                'proveedores.ver' => 'Ver proveedores',
                'proveedores.gestionar' => 'Crear y editar proveedores',
            ]],
            'inventario' => ['Bodega de repuestos', [
                'inventario.ver' => 'Ver repuestos y existencias',
                'inventario.ver_costos' => 'Ver costos y valor de la bodega',
                'inventario.gestionar' => 'Crear y editar repuestos',
            ]],
            'movimientos' => ['Movimientos de repuestos', [
                'movimientos.ver' => 'Ver movimientos y kárdex',
                'movimientos.crear' => 'Registrar compras y ajustes',
                'movimientos.aprobar' => 'Aprobar ajustes de existencia',
                'movimientos.anular' => 'Anular movimientos',
            ]],
            'conteos' => ['Conteos físicos', [
                'conteos.ver' => 'Ver conteos',
                'conteos.registrar' => 'Capturar cantidades contadas',
                'conteos.gestionar' => 'Abrir, aplicar y cancelar conteos',
            ]],
            'reportes' => ['Reportes', [
                'reportes.mantenimiento' => 'Reportes de mantenimiento',
                'reportes.repuestos' => 'Reportes de repuestos',
                'reportes.exportar' => 'Exportar a Excel',
            ]],
            'admin' => ['Administración', [
                'usuarios.ver' => 'Ver usuarios',
                'usuarios.gestionar' => 'Crear y editar usuarios',
                'roles.gestionar' => 'Editar roles y permisos',
                'catalogos.gestionar' => 'Editar catálogos (áreas, especialidades…)',
                'auditoria.ver' => 'Ver la auditoría',
            ]],
        ];
    }

    /** Lista plana permiso => descripción. */
    public static function todos(): array
    {
        $out = [];
        foreach (self::modulos() as [, $permisos]) {
            $out += $permisos;
        }

        return $out;
    }

    /**
     * Roles predefinidos: clave => [nombre, descripción, permisos].
     * '*' = todos los permisos. El super administrador además pasa cualquier
     * verificación por Gate::before (AppServiceProvider).
     */
    public static function roles(): array
    {
        $todos = array_keys(self::todos());
        $lecturaOperativa = [
            'dashboard.ver', 'maquinas.ver', 'ot.ver_todas', 'bitacora.ver', 'planes.ver',
            'herramientas.ver', 'proveedores.ver', 'inventario.ver', 'movimientos.ver', 'conteos.ver',
        ];

        return [
            self::SUPER_ADMIN => ['Super administrador',
                'Control total del sistema, incluido administrar a otros super administradores.', ['*']],
            'administrador' => ['Administrador',
                'Todo el sistema, salvo modificar a los super administradores.', ['*']],
            'gerente_general' => ['Gerente general',
                'Ve todo, reportes y costos, aprueba ajustes. No configura el sistema.',
                array_merge($lecturaOperativa, [
                    'ot.crear', 'inventario.ver_costos', 'movimientos.aprobar', 'asistencia.ver',
                    'reportes.mantenimiento', 'reportes.repuestos', 'reportes.exportar',
                    'usuarios.ver', 'auditoria.ver',
                ])],
            'gerente_mantenimiento' => ['Gerente de mantenimiento',
                'Todo el módulo de mantenimiento, bodega de repuestos, proveedores y reportes del área.',
                array_merge(
                    self::delModulo(['maquinas', 'ot', 'bitacora', 'planes', 'asistencia', 'herramientas', 'proveedores', 'inventario', 'movimientos', 'conteos']),
                    ['dashboard.ver', 'reportes.mantenimiento', 'reportes.repuestos', 'reportes.exportar', 'usuarios.ver']
                )],
            'admin_mantenimiento' => ['Administrador de mantenimiento',
                'Coordina: crea y asigna OT, planes, calendario, herramientas y lleva la bodega de repuestos.',
                ['dashboard.ver', 'maquinas.ver', 'maquinas.crear', 'maquinas.editar',
                    'ot.ver_todas', 'ot.crear', 'ot.editar', 'ot.asignar', 'ot.ejecutar', 'ot.cancelar',
                    'bitacora.ver', 'bitacora.crear', 'bitacora.editar',
                    'planes.ver', 'planes.gestionar', 'asistencia.ver', 'asistencia.gestionar',
                    'herramientas.ver', 'herramientas.gestionar', 'herramientas.asignar',
                    'proveedores.ver', 'proveedores.gestionar',
                    'inventario.ver', 'inventario.ver_costos', 'inventario.gestionar',
                    'movimientos.ver', 'movimientos.crear', 'movimientos.aprobar', 'movimientos.anular',
                    'conteos.ver', 'conteos.registrar', 'conteos.gestionar',
                    'reportes.mantenimiento', 'reportes.repuestos', 'reportes.exportar']],
            'tecnico' => ['Técnico de mantenimiento',
                'Mecánicos, electricistas y demás especialidades: ejecutan sus OT y registran en bitácora.',
                ['dashboard.ver', 'maquinas.ver', 'ot.ver_propias', 'ot.crear', 'ot.ejecutar',
                    'bitacora.ver', 'bitacora.crear', 'planes.ver', 'herramientas.ver',
                    'proveedores.ver', 'inventario.ver']],
            'supervisor_produccion' => ['Supervisor de producción',
                'Reporta fallas de máquinas y sigue su estado.',
                ['dashboard.ver', 'maquinas.ver', 'ot.ver_propias', 'ot.crear', 'bitacora.ver']],
            'contador' => ['Contador',
                'Solo lectura: repuestos valorizados, movimientos, costos de mantenimiento y reportes con exportación.',
                ['dashboard.ver', 'inventario.ver', 'inventario.ver_costos', 'movimientos.ver',
                    'conteos.ver', 'proveedores.ver', 'bitacora.ver', 'asistencia.ver',
                    'reportes.repuestos', 'reportes.mantenimiento', 'reportes.exportar']],
            'consulta' => ['Consulta',
                'Solo lectura de la operación, sin costos ni administración.', $lecturaOperativa],
            'personalizado' => ['Personalizado',
                'Sin permisos base: se marcan a mano los módulos que necesita (usuario adicional).', []],
        ];
    }

    public static function permisosDeRol(string $clave): array
    {
        $permisos = self::roles()[$clave][2] ?? [];

        return $permisos === ['*'] ? array_keys(self::todos()) : $permisos;
    }

    private static function delModulo(array $modulos): array
    {
        $out = [];
        foreach ($modulos as $m) {
            $out = array_merge($out, array_keys(self::modulos()[$m][1]));
        }

        return $out;
    }
}
