<?php

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\Maquina;
use App\Models\Producto;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Database\Seeder;

/**
 * Pedidos de ejemplo en todos los estados, hechos con PedidoService (el mismo flujo de la pantalla).
 * Usuarios nuevos: ventas (vendedora) y secretaria. Contraseña Tuboformas2026!
 *
 *   php artisan db:seed --class=DemoPedidosSeeder
 */
class DemoPedidosSeeder extends Seeder
{
    public function run(): void
    {
        if (Cliente::exists()) {
            $this->command?->info('DemoPedidosSeeder ya se había cargado.');

            return;
        }
        config(['mail.default' => 'log']);
        $this->call(RolesPermisosSeeder::class);

        $u = User::all()->keyBy('username');
        foreach ([['ventas', 'Sofía Ramírez', 'Vendedora'], ['secretaria', 'Gabriela Tax', 'Secretaria de ventas']] as [$user, $nombre, $puesto]) {
            $u[$user] = User::firstOrCreate(['username' => $user], ['name' => $nombre, 'password' => 'Tuboformas2026!', 'email' => "$user@tuboformas.test", 'puesto' => $puesto]);
            $u[$user]->syncRoles(['ventas']);
        }
        // Pilotos: personal de bodega que maneja los camiones de la empresa.
        foreach ([['walter', 'Walter Coyoy'], ['oscar', 'Óscar Méndez']] as [$user, $nombre]) {
            $u[$user] = User::firstOrCreate(['username' => $user], ['name' => $nombre, 'password' => 'Tuboformas2026!', 'email' => "$user@tuboformas.test", 'puesto' => 'Piloto', 'es_piloto' => true]);
            $u[$user]->syncRoles(['aux_bodega']);
        }
        $camiones = Maquina::whereIn('codigo', ['CAM1', 'CAM2'])->orderBy('codigo')->pluck('id')->all();

        $clientes = collect([
            ['Distribuidora Eléctrica Nacional', '5543210-8', 'Mario Gálvez', '2334-5566', 'Calzada Aguilar Batres 34-70 zona 11', 'Guatemala'],
            ['Ferretería La Económica', '1298765-4', 'Doña Lety Morales', '7761-2233', '4a. calle 12-30 zona 1', 'Quetzaltenango'],
            ['Constructora Los Álamos', '8876512-3', 'Ing. Pablo Recinos', '5566-7788', 'Km 22 carretera a El Salvador, obra Villas del Lago', 'Fraijanes'],
            ['Materiales Eléctricos del Norte', '3321987-0', 'Rony Caal', '7952-1100', 'Barrio El Centro, frente al parque', 'Cobán'],
            ['Ferretería El Martillo', '6654321-9', 'Julio Pineda', '2440-9988', '6a. avenida 3-15 zona 4', 'Mixco'],
            ['Electro Sur', '2210987-6', 'Karina Aldana', '7888-4455', '2a. calle 5-40 zona 2', 'Escuintla'],
            ['Instalaciones Eléctricas Pérez', 'CF', 'Don Chepe Pérez', '5123-4567', 'Colonia Primero de Julio, casa 12', 'Mixco'],
            ['Centro Ferretero Atitlán', '4432109-7', 'Lucía Tzoc', '7762-3344', 'Calle Santander', 'Panajachel'],
            ['Sucursal Zona 18', null, 'Encargado de sucursal', '2261-0000', 'Km 7 carretera al Atlántico, zona 18', 'Guatemala'],
            ['Hospital Regional (licitación)', '1110001-1', 'Depto. de compras', '7836-2211', 'Avenida Hospital', 'Chimaltenango'],
        ])->map(fn ($c) => Cliente::create(['nombre' => $c[0], 'nit' => $c[1], 'contacto' => $c[2], 'telefono' => $c[3], 'direccion' => $c[4], 'municipio' => $c[5]]))->keyBy('nombre');

        $svc = app(PedidoService::class);
        $pt = Bodega::where('codigo', 'PT')->value('id');
        $prod = Producto::with('presentaciones')->get()->keyBy('codigo');
        $l = fn (string $cod, float $cant, ?string $pres = null) => ['producto_id' => $prod[$cod]->id, 'cantidad' => $cant,
            'presentacion_id' => $pres ? $prod[$cod]->presentaciones->firstWhere('nombre', $pres)?->id : null];

        // [cliente, vendedor, días para la entrega, jornada, tipo, prioridad, líneas, hasta qué estado, notas]
        $casos = [
            ['Distribuidora Eléctrica Nacional', 'ventas', 0, 'tarde', 'ruta', 'urgente', [$l('PT-COP-34', 4, 'Bolsa x 300'), $l('PT-COD-34', 6, 'Bolsa x 100'), $l('PT-CON-34', 2, 'Bolsa x 300')], 'nuevo', 'Llamar 30 min antes de llegar.'],
            ['Ferretería La Económica', 'secretaria', 1, 'manana', 'transporte', 'normal', [$l('PT-COP-12', 3, 'Bolsa x 300'), $l('PT-CAJ-RECT', 4, 'Fardo x 50')], 'nuevo', 'Enviar por Cargo Expreso a Xela.'],
            ['Constructora Los Álamos', 'ventas', 2, null, 'ruta', 'normal', [$l('PT-CUR-34', 5, 'Bolsa x 100'), $l('PT-CAJ-OCT', 3, 'Fardo x 40'), $l('PT-COP-114', 2, 'Bolsa x 100')], 'nuevo', null],
            ['Electro Sur', 'ventas', 0, 'manana', 'ruta', 'normal', [$l('PT-COP-34-NA', 3, 'Bolsa x 300'), $l('PT-COD-12-NA', 4, 'Bolsa x 100'), $l('PT-CON-34-NA', 1, 'Bolsa x 300')], 'preparando', null],
            ['Materiales Eléctricos del Norte', 'secretaria', 1, null, 'transporte', 'normal', [$l('PT-ADM-12', 5, 'Bolsa x 200'), $l('PT-ADM-34', 5, 'Bolsa x 200'), $l('PT-ADH-12', 2, 'Bolsa x 200'), $l('PT-CAJ-CUAD', 2, 'Fardo x 40')], 'preparando_parcial', 'Factura a nombre de la empresa.'],
            ['Ferretería El Martillo', 'ventas', 0, 'tarde', 'recoge', 'normal', [$l('PT-COP-34', 2, 'Bolsa x 300'), $l('PT-CUR-12', 3, 'Bolsa x 100')], 'listo', 'Pasa a recoger Julio en pick-up.'],
            ['Instalaciones Eléctricas Pérez', 'secretaria', 0, 'tarde', 'ruta', 'normal', [$l('PT-COD-1', 2, 'Bolsa x 50'), $l('PT-CON-12', 450)], 'listo', null],
            ['Sucursal Zona 18', 'ventas', 0, 'manana', 'ruta', 'normal', [$l('PT-COP-12', 4, 'Bolsa x 300'), $l('PT-COP-34', 4, 'Bolsa x 300'), $l('PT-COD-34', 5, 'Bolsa x 100')], 'en_ruta', null],
            ['Centro Ferretero Atitlán', 'secretaria', -1, null, 'transporte', 'normal', [$l('PT-CAJ-RECT-NA', 3, 'Fardo x 50'), $l('PT-CUR-34-NA', 2, 'Bolsa x 100')], 'en_ruta', null],
            ['Distribuidora Eléctrica Nacional', 'ventas', -3, 'manana', 'ruta', 'normal', [$l('PT-COP-34-NA', 5, 'Bolsa x 300'), $l('PT-COD-34-NA', 5, 'Bolsa x 100')], 'entregado', null],
            ['Constructora Los Álamos', 'secretaria', -5, null, 'ruta', 'urgente', [$l('PT-CAJ-OCT', 4, 'Fardo x 40'), $l('PT-CUR-34', 4, 'Bolsa x 100')], 'entregado', null],
            ['Ferretería El Martillo', 'ventas', -2, null, 'recoge', 'normal', [$l('PT-CON-34', 2, 'Bolsa x 300')], 'entregado', null],
            ['Hospital Regional (licitación)', 'ventas', 6, null, 'ruta', 'normal', [$l('PT-CAJ-CUAD', 10, 'Fardo x 40'), $l('PT-CON-34', 5, 'Bolsa x 300')], 'cancelado', 'Esperar confirmación de licitación.'],
            ['Ferretería La Económica', 'secretaria', 4, 'manana', 'transporte', 'normal', [$l('PT-ADM-34', 3, 'Bolsa x 200'), $l('PT-COP-114', 3, 'Bolsa x 100')], 'nuevo', null],
        ];

        $bodega = ['abodega', 'auxbodega', 'gbodega'];
        foreach ($casos as $n => [$cliente, $vendedor, $dias, $jornada, $tipo, $prioridad, $lineas, $hasta, $notas]) {
            $c = $clientes[$cliente];
            $p = $svc->crear([
                'cliente_id' => $c->id, 'fecha_entrega' => today()->addDays($dias), 'jornada' => $jornada, 'tipo_entrega' => $tipo, 'prioridad' => $prioridad,
                'direccion_entrega' => $tipo === 'recoge' ? null : $c->direccion.', '.$c->municipio, 'contacto_nombre' => $c->contacto, 'contacto_telefono' => $c->telefono,
                'orden_compra' => $n % 3 === 0 ? 'OC-'.(4410 + $n) : null, 'condicion_pago' => $n % 2 ? 'credito' : 'contado', 'notas' => $notas, 'bodega_id' => $pt,
            ], $lineas, $u[$vendedor]);
            $arma = $u[$bodega[$n % 3]];
            if ($hasta === 'nuevo') {
                continue;
            }
            if ($hasta === 'cancelado') {
                $svc->cancelar($p, 'El cliente pospuso la compra hasta enero.', $u[$vendedor]);

                continue;
            }
            $svc->tomar($p, $arma);
            $ls = $p->lineas()->get();
            $marcar = $hasta === 'preparando' ? $ls->take(1) : ($hasta === 'preparando_parcial' ? $ls->take(2) : $ls);
            foreach ($marcar as $i => $linea) {
                // En uno de los "listos" una línea salió incompleta.
                $cant = $hasta === 'listo' && $n === 6 && $i === 1 ? 300.0 : null;
                $svc->marcarLinea($linea, true, $cant, $arma);
            }
            if (in_array($hasta, ['preparando', 'preparando_parcial'])) {
                continue;
            }
            $svc->marcarListo($p, $arma);
            if ($hasta === 'listo') {
                continue;
            }
            if ($tipo !== 'recoge') {
                $svc->despachar($p, $tipo === 'ruta'
                    ? ['vehiculo_id' => $camiones[$n % 2] ?? null, 'piloto_id' => $u[$n % 2 ? 'oscar' : 'walter']->id]
                    : ['empresa' => 'Cargo Expreso', 'guia' => 'CE-'.(880100 + $n)], $u['abodega']);
            }
            if ($hasta === 'entregado') {
                $svc->entregar($p, ['recibido_por' => $c->contacto], $p->fresh()->piloto_id ? User::find($p->fresh()->piloto_id) : $u['abodega']);
            }
            // Fechas reales del recorrido (el servicio usa "ahora").
            $base = today()->addDays($dias)->subDay()->setTime(8, 0);
            $p->forceFill(['created_at' => $base->copy()->subDay(), 'preparando_at' => $base->copy()->addHour(), 'listo_at' => $base->copy()->addHours(3),
                'en_ruta_at' => $p->en_ruta_at ? $base->copy()->addHours(5) : null, 'entregado_at' => $p->entregado_at ? $base->copy()->addDay()->setTime(11, 20) : null])->save();
        }
    }
}
