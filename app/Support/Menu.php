<?php

namespace App\Support;

use App\Models\Movimiento;
use App\Models\OrdenTrabajo;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Viaje;

/**
 * Menú lateral. Cada entrada se muestra solo si el usuario tiene alguno de sus permisos.
 * Algunas llevan un contador (OT atrasadas, ajustes por aprobar, productos bajo el mínimo)
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
            'Bodega' => [
                ['Ingreso rápido', 'bodega.ingreso-rapido', 'escanear', ['movimientos.crear'], 'bodega.ingreso-rapido'],
                ['Etiquetas QR', 'bodega.etiquetas', 'qr', ['inventario.gestionar', 'movimientos.crear'], 'bodega.etiquetas'],
                ['Inventario', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*', 'bajo_minimo'],
                ['Movimientos', 'movimientos.index', 'flechas', ['movimientos.ver'], 'movimientos.*', 'por_aprobar'],
                ['Conteos físicos', 'conteos.index', 'conteo', ['conteos.ver'], 'conteos.*'],
            ],
            'Pedidos' => [
                ['Pedidos', 'pedidos.index', 'camion', ['pedidos.ver', 'pedidos.ver_todos', 'pedidos.preparar'], 'pedidos.*', 'pedidos'],
                ['Viajes de entrega', 'viajes.index', 'ubicacion', ['pedidos.preparar'], 'viajes.*', 'viajes'],
                ['Clientes', 'clientes.index', 'usuarios', ['clientes.gestionar', 'pedidos.crear', 'pedidos.ver_todos'], 'clientes.*'],
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
                ] + self::contador($i[5] ?? null, $u), $visibles)];
            }
        }

        return $out;
    }

    /**
     * Barra inferior del celular: 4 accesos según el trabajo de cada quien y una acción al centro
     * (Escanear para bodega, Reportar falla para mantenimiento). "Más" abre el menú completo.
     *
     * @return array{items: array, centro: ?array}
     */
    public static function barraInferior(User $u): array
    {
        $rol = $u->rolPrincipal()?->name ?? '';
        $esBodega = str_contains($rol, 'bodega') || (! $u->canAny(['ot.ver_todas', 'ot.ver_propias']) && $u->can('movimientos.crear'));
        $esVentas = ! $esBodega && ($rol === 'ventas' || (! $u->canAny(['ot.ver_todas', 'ot.ver_propias']) && $u->can('pedidos.crear')));

        $candidatos = match (true) {
            // El piloto tiene sus viajes a la mano; el resto de bodega, el inventario.
            $esBodega && $u->es_piloto => [
                ['Inicio', 'dashboard', 'tablero', ['dashboard.ver'], 'dashboard'],
                ['Viajes', 'viajes.index', 'ubicacion', ['pedidos.preparar'], 'viajes.*'],
                ['Pedidos', 'pedidos.index', 'camion', ['pedidos.preparar'], 'pedidos.*'],
                ['Inventario', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*'],
            ],
            $esBodega => [
                ['Inicio', 'dashboard', 'tablero', ['dashboard.ver'], 'dashboard'],
                ['Pedidos', 'pedidos.index', 'camion', ['pedidos.preparar'], 'pedidos.*'],
                ['Inventario', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*'],
                ['Movimientos', 'movimientos.index', 'flechas', ['movimientos.ver'], 'movimientos.*'],
            ],
            $esVentas => [
                ['Inicio', 'dashboard', 'tablero', ['dashboard.ver'], 'dashboard'],
                ['Pedidos', 'pedidos.index', 'camion', ['pedidos.ver', 'pedidos.ver_todos'], 'pedidos.index|pedidos.show|pedidos.edit'],
                ['Clientes', 'clientes.index', 'usuarios', ['clientes.gestionar', 'pedidos.crear'], 'clientes.*'],
                ['Inventario', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*'],
            ],
            default => [
                ['Inicio', 'dashboard', 'tablero', ['dashboard.ver'], 'dashboard'],
                ['Órdenes', 'ot.index', 'portapapeles', ['ot.ver_todas', 'ot.ver_propias'], 'ot.index|ot.show|ot.edit|ot.kanban|calendario'],
                ['Máquinas', 'maquinas.index', 'maquina', ['maquinas.ver'], 'maquinas.*|bitacora.*'],
                ['Inventario', 'productos.index', 'cajas', ['inventario.ver'], 'productos.*'],
            ],
        };
        $centro = match (true) {
            $esBodega && $u->can('movimientos.crear') => ['Escanear', 'bodega.ingreso-rapido', 'escanear', 'bodega.*'],
            $esVentas && $u->can('pedidos.crear') => ['Pedido', 'pedidos.create', 'mas', 'pedidos.create'],
            $u->can('ot.crear') => ['Reportar', 'ot.create', 'mas', 'ot.create'],
            default => null,
        };

        $items = array_slice(array_values(array_filter($candidatos, fn ($i) => $u->canAny($i[3]))), 0, $centro ? 2 : 3);
        // Con acción central quedan 2 + centro + 1 + "Más"; sin ella, 3 + "Más".
        if ($centro) {
            $resto = array_values(array_filter($candidatos, fn ($i) => $u->canAny($i[3]) && ! in_array($i, $items, true)));
            if ($resto) {
                $items[] = $resto[0];
            }
        }
        $fmt = fn ($i) => ['nombre' => $i[0], 'ruta' => $i[1], 'icono' => $i[2], 'activo' => $i[4] ?? $i[3]] + self::contador(
            ['ot.index' => 'atrasadas', 'movimientos.index' => 'por_aprobar', 'productos.index' => 'bajo_minimo', 'pedidos.index' => 'pedidos', 'viajes.index' => $u->es_piloto ? 'mis_viajes' : 'viajes'][$i[1]] ?? null, $u);

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
            'viajes' => Viaje::where('estado', 'en_ruta')->count(),
            'mis_viajes' => Pedido::where('estado', 'en_ruta')->whereHas('viaje', fn ($q) => $q->where('piloto_id', $u->id)->where('estado', 'en_ruta'))->count(),
            'pedidos' => $u->can('pedidos.preparar')
                ? Pedido::whereIn('estado', ['nuevo', 'preparando', 'listo'])->count()
                : Pedido::visiblesPara($u)->activos()->count(),
            'bajo_minimo' => Producto::where('activo', true)->where('stock_minimo', '>', 0)
                ->whereRaw('stock_minimo > (select coalesce(sum(e.cantidad), 0) from existencias e where e.producto_id = productos.id)')->count(),
            default => 0,
        };
        if (! $n) {
            return [];
        }

        return match ($clave) {
            'atrasadas' => ['contador' => $n, 'tono' => 'rojo', 'ayuda' => $n === 1 ? '1 OT atrasada' : "$n OT atrasadas"],
            'por_aprobar' => ['contador' => $n, 'tono' => 'ambar', 'ayuda' => $n === 1 ? '1 ajuste por aprobar' : "$n ajustes por aprobar"],
            'viajes' => ['contador' => $n, 'tono' => 'ambar', 'ayuda' => $n === 1 ? '1 camión en ruta' : "$n camiones en ruta"],
            'mis_viajes' => ['contador' => $n, 'tono' => 'rojo', 'ayuda' => $n === 1 ? '1 entrega pendiente' : "$n entregas pendientes"],
            'pedidos' => ['contador' => $n, 'tono' => 'azul', 'ayuda' => $u->can('pedidos.preparar') ? ($n === 1 ? '1 pedido por despachar' : "$n pedidos por despachar") : ($n === 1 ? '1 pedido en curso' : "$n pedidos en curso")],
            'bajo_minimo' => ['contador' => $n, 'tono' => 'ambar', 'ayuda' => $n === 1 ? '1 producto bajo el mínimo' : "$n productos bajo el mínimo"],
        };
    }
}
