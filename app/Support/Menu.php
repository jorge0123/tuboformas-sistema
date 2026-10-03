<?php

namespace App\Support;

use App\Models\Movimiento;
use App\Models\OrdenTrabajo;
use App\Models\Producto;
use App\Models\User;

/**
 * Menú lateral. Cada entrada se muestra solo si el usuario tiene alguno de sus permisos.
 * Algunas llevan un contador (OT atrasadas, ajustes por aprobar, repuestos bajo el mínimo)
 * para que el menú avise sin tener que entrar a cada pantalla.
 */
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
                ['Órdenes de trabajo', 'ot.index', 'portapapeles', ['ot.ver_todas', 'ot.ver_propias'], 'ot.index|ot.show|ot.create|ot.edit', 'atrasadas'],
                ['Tablero Kanban', 'ot.kanban', 'kanban', ['ot.ver_todas', 'ot.ver_propias'], 'ot.kanban'],
                ['Calendario', 'calendario', 'calendario', ['planes.ver', 'ot.ver_todas', 'ot.ver_propias'], 'calendario'],
                ['Planes preventivos', 'planes.index', 'repetir', ['planes.ver'], 'planes.*'],
                ['Bitácora', 'bitacora.index', 'libro', ['bitacora.ver'], 'bitacora.*'],
                ['Herramientas', 'herramientas.index', 'martillo', ['herramientas.ver'], 'herramientas.*'],
                ['Proveedores', 'proveedores.index', 'camion', ['proveedores.ver'], 'proveedores.*'],
            ],
            'Bodega de repuestos' => [
                ['Repuestos', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*', 'bajo_minimo'],
                ['Movimientos', 'movimientos.index', 'flechas', ['movimientos.ver'], 'movimientos.*', 'por_aprobar'],
                ['Conteos físicos', 'conteos.index', 'conteo', ['conteos.ver'], 'conteos.*'],
            ],
            'Reportes' => [
                ['Mantenimiento', 'reportes.mantenimiento', 'grafica', ['reportes.mantenimiento'], 'reportes.mantenimiento'],
                ['Repuestos', 'reportes.repuestos', 'tendencia', ['reportes.repuestos'], 'reportes.repuestos'],
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
                ] + self::contador($i[5] ?? null, $u), $visibles)];
            }
        }

        return $out;
    }

    /**
     * Barra inferior del celular: 3 accesos y "Reportar falla" al centro para quien puede crear OT.
     * "Más" abre el menú completo.
     *
     * @return array{items: array, centro: ?array}
     */
    public static function barraInferior(User $u): array
    {
        $candidatos = [
            ['Inicio', 'dashboard', 'tablero', ['dashboard.ver'], 'dashboard'],
            ['Órdenes', 'ot.index', 'portapapeles', ['ot.ver_todas', 'ot.ver_propias'], 'ot.index|ot.show|ot.edit|ot.kanban|calendario'],
            ['Máquinas', 'maquinas.index', 'maquina', ['maquinas.ver'], 'maquinas.*|bitacora.*'],
            ['Repuestos', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*|movimientos.*|conteos.*'],
        ];
        $centro = $u->can('ot.crear') ? ['Reportar', 'ot.create', 'mas', 'ot.create'] : null;

        $visibles = array_values(array_filter($candidatos, fn ($i) => $u->canAny($i[3])));
        // Con acción central quedan 2 + centro + 1 + "Más"; sin ella, 3 + "Más".
        $items = array_slice($visibles, 0, 3);
        $fmt = fn ($i) => ['nombre' => $i[0], 'ruta' => $i[1], 'icono' => $i[2], 'activo' => $i[4]] + self::contador(
            ['ot.index' => 'atrasadas', 'productos.index' => 'bajo_minimo'][$i[1]] ?? null, $u);

        return [
            'items' => array_map($fmt, $items),
            'centro' => $centro ? ['nombre' => $centro[0], 'ruta' => $centro[1], 'icono' => $centro[2], 'activo' => $centro[3]] : null,
        ];
    }

    /** [contador, tono, ayuda] de una entrada; vacío si no aplica o está en cero. */
    private static function contador(?string $clave, User $u): array
    {
        static $cache = [];
        if ($clave && isset($cache[$u->id][$clave])) {
            return $cache[$u->id][$clave];
        }

        return $cache[$u->id][$clave ?? ''] = self::contar($clave, $u);
    }

    private static function contar(?string $clave, User $u): array
    {
        $n = match ($clave) {
            'atrasadas' => OrdenTrabajo::visiblesPara($u)->enSituacion('atrasada')->count(),
            'por_aprobar' => $u->can('movimientos.aprobar') ? Movimiento::where('estado', 'pendiente')->count() : 0,
            'bajo_minimo' => Producto::where('activo', true)->bajoMinimo()->count(),
            default => 0,
        };
        if (! $n) {
            return [];
        }

        return match ($clave) {
            'atrasadas' => ['contador' => $n, 'tono' => 'rojo', 'ayuda' => $n === 1 ? '1 OT atrasada' : "$n OT atrasadas"],
            'por_aprobar' => ['contador' => $n, 'tono' => 'ambar', 'ayuda' => $n === 1 ? '1 ajuste por aprobar' : "$n ajustes por aprobar"],
            'bajo_minimo' => ['contador' => $n, 'tono' => 'ambar', 'ayuda' => $n === 1 ? '1 repuesto bajo el mínimo' : "$n repuestos bajo el mínimo"],
        };
    }
}
