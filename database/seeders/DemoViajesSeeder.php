<?php

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\Maquina;
use App\Models\Producto;
use App\Models\User;
use App\Models\Viaje;
use App\Services\PedidoService;
use App\Services\ViajeService;
use Illuminate\Database\Seeder;

/**
 * Viajes de ejemplo: uno terminado (ayer) y uno en ruta con una parada ya entregada; además
 * deja pedidos listos en Escuintla y Mixco para armar un viaje nuevo.
 *
 *   php artisan db:seed --class=DemoViajesSeeder
 */
class DemoViajesSeeder extends Seeder
{
    public function run(): void
    {
        if (Viaje::exists() || ! Cliente::exists()) {
            return;
        }
        config(['mail.default' => 'log']);
        $u = User::all()->keyBy('username');
        $ped = app(PedidoService::class);
        $via = app(ViajeService::class);
        $pt = Bodega::where('codigo', 'PT')->value('id');
        $cam = Maquina::whereIn('codigo', ['CAM1', 'CAM2'])->pluck('id', 'codigo');
        $prod = Producto::with('presentaciones')->get()->keyBy('codigo');
        $l = fn ($cod, $n, $pres) => ['producto_id' => $prod[$cod]->id, 'cantidad' => $n, 'presentacion_id' => $prod[$cod]->presentaciones->firstWhere('nombre', $pres)?->id];

        // Un pedido listo para ruta, armado por el auxiliar.
        $listo = function (string $cliente, array $lineas, string $vendedor, int $dias = 0) use ($ped, $u, $pt) {
            $c = Cliente::where('nombre', $cliente)->firstOrFail();
            $p = $ped->crear(['cliente_id' => $c->id, 'fecha_entrega' => today()->addDays($dias), 'tipo_entrega' => 'ruta', 'prioridad' => 'normal',
                'direccion_entrega' => $c->direccion.', '.$c->municipio, 'contacto_nombre' => $c->contacto, 'contacto_telefono' => $c->telefono, 'bodega_id' => $pt], $lineas, $u[$vendedor]);
            $ped->tomar($p, $u['auxbodega']);
            foreach ($p->lineas as $linea) {
                $ped->marcarLinea($linea, true, null, $u['auxbodega']);
            }
            $ped->marcarListo($p->fresh(), $u['auxbodega']);

            return $p->fresh();
        };

        // Viaje de ayer, terminado: las dos paradas entregadas.
        $a = $listo('Ferretería El Martillo', [$l('PT-COP-12', 2, 'Bolsa x 300'), $l('PT-CUR-12', 2, 'Bolsa x 100')], 'ventas', -1);
        $b = $listo('Instalaciones Eléctricas Pérez', [$l('PT-CON-12', 1, 'Bolsa x 300')], 'secretaria', -1);
        $v1 = $via->salir($cam['CAM1'], $u['walter']->id, [$a->id, $b->id], null, $u['abodega']);
        $via->entregar($a->fresh(), ['recibido_por' => 'Julio Pineda'], $u['walter']);
        $via->entregar($b->fresh(), ['recibido_por' => 'Don Chepe Pérez'], $u['walter']);
        $v1->forceFill(['salida_at' => today()->subDay()->setTime(8, 10), 'regreso_at' => today()->subDay()->setTime(12, 45)])->save();

        // Viaje de hoy, en ruta hacia el sur: la primera parada ya se entregó.
        $c = $listo('Sucursal Zona 18', [$l('PT-COP-34', 2, 'Bolsa x 300'), $l('PT-COD-34', 3, 'Bolsa x 100')], 'ventas');
        $d = $listo('Electro Sur', [$l('PT-COP-34-NA', 2, 'Bolsa x 300'), $l('PT-CUR-34-NA', 2, 'Bolsa x 100')], 'ventas');
        $e = $listo('Distribuidora Eléctrica Nacional', [$l('PT-CAJ-RECT', 2, 'Fardo x 50')], 'secretaria');
        $v2 = $via->salir($cam['CAM2'], $u['oscar']->id, [$c->id, $e->id, $d->id], 'Pasar por Escuintla de último; Electro Sur cierra a las 17:00.', $u['abodega']);
        $via->entregar($c->fresh(), ['recibido_por' => 'Encargado de sucursal'], $u['oscar']);
        $v2->forceFill(['salida_at' => now()->subHours(2)])->save();

        // Listos para armar el siguiente viaje (misma zona: Mixco).
        $listo('Ferretería El Martillo', [$l('PT-ADM-12', 2, 'Bolsa x 200')], 'ventas', 1);
        $listo('Instalaciones Eléctricas Pérez', [$l('PT-CAJ-OCT', 1, 'Fardo x 40')], 'secretaria', 1);
    }
}
