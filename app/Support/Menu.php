<?php

namespace App\Support;

use App\Models\User;

/** Menú lateral. Cada entrada se muestra solo si el usuario tiene alguno de sus permisos. */
class Menu
{
    public static function para(User $u): array
    {
        $grupos = [
            null => [
                ['Inicio', 'dashboard', 'tablero', ['dashboard.ver'], 'dashboard'],
            ],
            'Mantenimiento' => [
                ['Máquinas', 'maquinas.index', 'maquina', ['maquinas.ver'], 'maquinas.*'],
                ['Órdenes de trabajo', 'ot.index', 'portapapeles', ['ot.ver_todas', 'ot.ver_propias'], 'ot.index|ot.show|ot.create|ot.edit'],
                ['Tablero Kanban', 'ot.kanban', 'kanban', ['ot.ver_todas', 'ot.ver_propias'], 'ot.kanban'],
                ['Calendario', 'calendario', 'calendario', ['planes.ver', 'ot.ver_todas', 'ot.ver_propias'], 'calendario'],
                ['Planes preventivos', 'planes.index', 'repetir', ['planes.ver'], 'planes.*'],
                ['Bitácora', 'bitacora.index', 'libro', ['bitacora.ver'], 'bitacora.*'],
                ['Herramientas', 'herramientas.index', 'martillo', ['herramientas.ver'], 'herramientas.*'],
                ['Proveedores', 'proveedores.index', 'camion', ['proveedores.ver'], 'proveedores.*'],
            ],
            'Bodega' => [
                ['Inventario', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*'],
                ['Movimientos', 'movimientos.index', 'flechas', ['movimientos.ver'], 'movimientos.*'],
                ['Conteos físicos', 'conteos.index', 'conteo', ['conteos.ver'], 'conteos.*'],
            ],
            'Reportes' => [
                ['Mantenimiento', 'reportes.mantenimiento', 'grafica', ['reportes.mantenimiento'], 'reportes.mantenimiento'],
                ['Bodega', 'reportes.bodega', 'tendencia', ['reportes.bodega'], 'reportes.bodega'],
            ],
            'Administración' => [
                ['Usuarios', 'usuarios.index', 'usuarios', ['usuarios.ver'], 'usuarios.*'],
                ['Roles y permisos', 'roles.index', 'escudo', ['roles.gestionar'], 'roles.*'],
                ['Catálogos', 'catalogos.index', 'lista', ['catalogos.gestionar'], 'catalogos.*'],
                ['Auditoría', 'auditoria', 'historial', ['auditoria.ver'], 'auditoria'],
            ],
        ];

        $out = [];
        foreach ($grupos as $titulo => $items) {
            $visibles = array_values(array_filter($items, fn ($i) => $u->canAny($i[3])));
            if ($visibles) {
                $out[] = ['titulo' => $titulo, 'items' => array_map(fn ($i) => [
                    'nombre' => $i[0], 'ruta' => $i[1], 'icono' => $i[2], 'activo' => $i[4],
                ], $visibles)];
            }
        }

        return $out;
    }
}
