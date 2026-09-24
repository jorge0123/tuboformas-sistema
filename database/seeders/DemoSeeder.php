<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Bitacora;
use App\Models\Bodega;
use App\Models\CategoriaProducto;
use App\Models\Especialidad;
use App\Models\Herramienta;
use App\Models\Maquina;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\OrdenTrabajoService;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo tomados de los Excel que mostró Tuboformas (listado de máquinas,
 * hoja técnica de Inyectora 1 y Filtradora de Aceite 1, registro de mantenimiento y
 * hoja de tareas). Los nombres de técnicos son los que aparecían en esas hojas.
 * Todos los usuarios de ejemplo tienen la contraseña Tuboformas2026!
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Maquina::exists()) {
            return; // ya se cargó
        }
        // Los avisos de los datos de ejemplo no se envían por correo.
        config(['mail.default' => 'log']);

        $area = fn (string $n) => Area::where('nombre', $n)->value('id');
        $esp = fn (string $n) => Especialidad::where('nombre', $n)->value('id');

        // ── Usuarios de ejemplo, uno por rol ────────────────────────────────
        $u = [];
        foreach ([
            ['gerente', 'Joao Gómez', 'gerente_general', 'Gerente general', null],
            ['gmantto', 'Aldair Marroquín', 'gerente_mantenimiento', 'Gerente de mantenimiento', null],
            ['amantto', 'Bryan Lemus', 'admin_mantenimiento', 'Coordinador de mantenimiento', 'Mecánico'],
            ['kenneth', 'Kenneth López', 'tecnico', 'Técnico', 'Eléctrico'],
            ['miguel', 'Miguel Ramírez', 'tecnico', 'Técnico', 'Eléctrico'],
            ['selvin', 'Selvin Ambrosio', 'tecnico', 'Técnico', 'Mecánico'],
            ['canche', 'Carlos Canché', 'tecnico', 'Técnico', 'Soldadura'],
            ['djose', 'José Pérez', 'tecnico', 'Técnico', 'Hidráulico / Neumático'],
            ['gbodega', 'Marta Castillo', 'gerente_bodega', 'Gerente de bodega', null],
            ['abodega', 'Luis Herrera', 'admin_bodega', 'Encargado de bodega', null],
            ['auxbodega', 'Pedro Xicará', 'aux_bodega', 'Auxiliar de bodega', null],
            ['supervisor', 'Ana Morales', 'supervisor_produccion', 'Supervisora de producción', null],
            ['contador', 'Roberto Chávez', 'contador', 'Contador', null],
        ] as [$user, $nombre, $rol, $puesto, $especialidad]) {
            $u[$user] = User::create([
                'username' => $user, 'name' => $nombre, 'password' => 'Tuboformas2026!',
                'email' => "$user@tuboformas.test", 'puesto' => $puesto,
                'especialidad_id' => $especialidad ? $esp($especialidad) : null,
            ]);
            $u[$user]->assignRole($rol);
        }
        $admin = User::where('username', 'admin')->first();

        // ── Listado de Maquinas Tuboformas ──────────────────────────────────
        $filas = [
            [1, 'Taller de Maquinado', 'AF1', 'Afiladora 1', 'GORTON', 'Model 375 110Vac', 'operativa'],
            [2, 'Empaque', 'CA1', 'Conectora Automática', 'CROWN', '220V/50Hz', 'operativa'],
            [3, 'Extrusión', 'BTF', 'Bobinadora de Tubería Flexible', 'FULLWN', null, 'operativa'],
            [4, 'Taller Mecánico', 'BM1', 'Barreno Magnético', null, '220V AC Monofásico', 'operativa'],
            [5, 'Vehículos', 'CAM1', 'Camión', 'KIA K3000S', '3 toneladas', 'operativa'],
            [6, 'Vehículos', 'CAM2', 'Camión', 'FOTON', '3 toneladas', 'operativa'],
            [7, 'Servicios', 'CH1', 'Chiller 1', 'ADVANTAGE', '480Vac 40A', 'baja'],
            [8, 'Servicios', 'BA2', 'Cisterna Jardín 2', 'AquaPro', '110Vac Bomba jardín trasero', 'operativa'],
            [9, 'Servicios', 'BA1', 'Cisterna Principal 1', 'STA-RITE', 'HP 3/4 220V 6.2A Bomba principal', 'operativa'],
            [10, 'Servicios', 'COMP1', 'Compresor Azul', 'CHPOWER', '135 psi MAX 60 galón 220Vac 15A', 'baja'],
            [11, 'Servicios', 'COMPIR', 'Compresor de tornillo', 'Ingersoll Rand', '128 psi MAX 220Vac trifásico 45A', 'operativa'],
            [12, 'Servicios', 'COMPHZ', 'Compresor de tornillo 2', 'Hertz', '160 psi max 480VAC trifásico 15A', 'operativa'],
            [13, 'Servicios', 'COMP2', 'Compresor Gris', 'Ingersoll Rand', '200 psi MAX 80 galón 440Vac 7.5A', 'baja'],
            [14, 'Servicios', 'COMPMOVIL', 'Compresor Móvil', 'Tiambao', '130 psi Max 110v 15A', 'operativa'],
            [15, 'Servicios', 'COMP3', 'Compresor Verde', null, '191 lb 220Vac 15A', 'baja'],
            [16, 'Empaque', 'MCA', 'Contadora Automática', 'Hefei the One', '220 V', 'operativa'],
            [17, 'Taller Mecánico', 'CT1', 'Cortador de Tubos 1', null, 'Motor 110Vac', 'operativa'],
            [18, 'Taller Mecánico', 'DZC', 'Desbrozadora de combustible', 'Dong Cheng', 'Combustible, 1250W', 'operativa'],
            [19, 'Bodega', 'ELV1', 'Elevador', null, null, 'operativa'],
            [20, 'Termoformado', 'ENC1', 'Encampanadora 1', 'Tuboformas', 'Medidas 1/2" - 3/4"', 'operativa'],
            [21, 'Termoformado', 'ENC2', 'Encampanadora 2', 'Tuboformas', 'Medidas 1/2" - 3/4"', 'operativa'],
            [22, 'Termoformado', 'ENC3', 'Encampanadora 3', 'Tuboformas', 'Medidas 1/2" - 3/4"', 'operativa'],
            [23, 'Inyección', 'INY1', 'Inyectora 1', 'Cincinnati Milacron', 'MT165', 'operativa'],
            [24, 'Inyección', 'INY2', 'Inyectora 2', null, null, 'operativa'],
            [25, 'Inyección', 'INY3', 'Inyectora 3', null, null, 'operativa'],
            [26, 'Inyección', 'INY4', 'Inyectora 4', null, null, 'operativa'],
            [27, 'Inyección', 'INYBOY', 'Inyectora Boy', 'BOY', null, 'operativa'],
            [28, 'Servicios', 'FA1', 'Filtradora de Aceite 1', null, null, 'operativa'],
            [29, 'Taller de Maquinado', 'FR1', 'Fresadora 1', null, null, 'operativa'],
            [30, 'Taller de Maquinado', 'FR2', 'Fresadora 2', null, null, 'operativa'],
        ];
        $maq = [];
        foreach ($filas as [$n, $a, $cod, $nombre, $marca, $obs, $estado]) {
            $maq[$cod] = Maquina::create([
                'numero' => $n, 'area_id' => $area($a), 'codigo' => $cod, 'nombre' => $nombre,
                'marca' => $marca, 'observaciones' => $obs, 'estado' => $estado,
                'criticidad' => in_array($a, ['Inyección', 'Termoformado', 'Extrusión']) ? 'A' : ($a === 'Vehículos' ? 'C' : 'B'),
            ]);
        }

        // ── Hoja técnica: Cincinnati Milacron MT165 (Inyectora 1) ───────────
        $iny = $maq['INY1'];
        $iny->update(['modelo' => 'VT165-11', 'serie' => 'T36A0398062', 'anio' => 1998, 'horometro' => 48210]);
        $iny->componentes()->createMany([
            ['nombre' => 'Inyectora', 'orden' => 1, 'especificaciones' => [
                ['clave' => 'Producto', 'valor' => 'VT165-11'], ['clave' => 'No. de serie', 'valor' => 'T36A0398062'],
                ['clave' => 'Año de fabricación', 'valor' => '1998'], ['clave' => 'Peso', 'valor' => '14500 lb'],
                ['clave' => 'Voltaje', 'valor' => '460 Vac'], ['clave' => 'Frecuencia', 'valor' => '60 Hz'],
                ['clave' => 'Corriente', 'valor' => '32 A'], ['clave' => 'Control', 'valor' => '115 VAC'],
                ['clave' => 'Shot size', 'valor' => '11 Oz'], ['clave' => 'Filtro respiradero', 'valor' => 'Schroeder ABF-3/10'],
                ['clave' => 'Diagramas lógicos', 'valor' => '303401'],
            ]],
            ['nombre' => 'Motor eléctrico trifásico', 'orden' => 2, 'especificaciones' => [
                ['clave' => 'Marca', 'valor' => 'Baldor'], ['clave' => 'No. Cat', 'valor' => '3983109-1'],
                ['clave' => 'Frame', 'valor' => '284TD'], ['clave' => 'SER.', 'valor' => '09906'],
                ['clave' => 'No. de fases', 'valor' => '3'], ['clave' => 'Potencia', 'valor' => '25 HP'],
                ['clave' => 'Voltaje', 'valor' => '230/460 Vac'], ['clave' => 'Frecuencia', 'valor' => '60 Hz'],
                ['clave' => 'Corriente', 'valor' => '64/32 A'], ['clave' => 'RPM', 'valor' => '1760'],
                ['clave' => 'Factor de servicio', 'valor' => '1.15'], ['clave' => 'Eficiencia nominal', 'valor' => '90.2 %'],
                ['clave' => 'P.F.', 'valor' => '81 %'], ['clave' => 'Clase', 'valor' => 'B'], ['clave' => 'Código', 'valor' => 'H'],
            ]],
        ]);

        // ── Hoja técnica: Filtradora de Aceite 1 ────────────────────────────
        $fa = $maq['FA1'];
        $fa->componentes()->createMany([
            ['nombre' => 'Motor', 'orden' => 1, 'especificaciones' => [
                ['clave' => 'Marca', 'valor' => 'Baldor'], ['clave' => 'Modelo', 'valor' => '48YZ'],
                ['clave' => 'No. de serie', 'valor' => 'W5-94'], ['clave' => 'Voltaje', 'valor' => '110V/220V'],
                ['clave' => 'Corriente', 'valor' => '6 / 3.2 A'], ['clave' => 'No. de fases', 'valor' => '1'],
                ['clave' => 'Frecuencia', 'valor' => '60 Hz'],
            ]],
            ['nombre' => 'Bomba', 'orden' => 2, 'especificaciones' => [
                ['clave' => 'Marca', 'valor' => 'Viking'], ['clave' => 'Modelo', 'valor' => 'SG-0518-G0V'],
                ['clave' => 'Serie', 'valor' => '10488484'],
            ]],
            ['nombre' => 'Celda electrostática', 'orden' => 3, 'especificaciones' => [
                ['clave' => 'Modelo', 'valor' => 'R61CS-115'], ['clave' => 'Fecha de fabricación', 'valor' => '07/2007'],
                ['clave' => 'Presión máx. retorno', 'valor' => '10 psi'],
            ]],
        ]);

        // ── Proveedores que aparecen en el registro de mantenimiento ────────
        $prov = [];
        foreach ([
            ['Yamasa', ['repuestos']], ['Hydraserv', ['servicios', 'repuestos']],
            ['HySeal', ['servicios']], ['Hidra Serv', ['servicios']],
            ['Tubería y Plásticos S.A.', ['materia_prima']], ['Eléctrica Industrial Guatemala', ['repuestos']],
        ] as [$nombre, $tipos]) {
            $prov[$nombre] = Proveedor::create(['nombre' => $nombre, 'tipos' => $tipos]);
        }

        // ── Registro de Mantenimiento (Inyectora 1 y Filtradora) ────────────
        foreach ([
            ['2021-08-14', 'Bloque central de prensa', 'Cambio de manguera puerto X14', 'Proveedor Yamasa', 'Yamasa', false],
            ['2021-08-14', 'Bloque central de prensa', 'Cambio de manguera puerto X15', 'Proveedor Yamasa', 'Yamasa', false],
            ['2021-08-14', 'Bloque central de prensa', 'Cambio de manguera puerto X12', 'Proveedor Yamasa', 'Yamasa', false],
            ['2021-08-13', 'Bloque central de prensa', 'Cambio de O-rings bloque electroválvula 1', null, 'Hydraserv', false],
            ['2021-08-14', 'Prensa', 'Mantenimiento a cilindro principal de prensa', 'Cambio de empaques y honeado', 'Hydraserv', false],
            ['2021-08-21', 'Cañón', 'Mantenimiento a cilindros laterales (azul) lado operador', '08/13/21-08/21/21 Mantenimiento defectuoso. Reclamo por garantía.', 'HySeal', true],
            ['2021-08-21', 'Cañón', 'Mantenimiento a cilindros laterales (azul) lado servicios', '08/13/21-08/21/21 Mantenimiento defectuoso. Reclamo por garantía.', 'HySeal', true],
            ['2021-08-16', 'Sistema de enfriamiento', 'Limpieza de manifolds de agua de enfriamiento (entrada y salida)', null, null, false],
            ['2021-08-16', 'Sistema de enfriamiento', 'Limpieza de intercambiador de calor de aceite', null, null, false],
            ['2021-09-14', 'Unidad de cierre', 'Mantenimiento a cilindro hidráulico placa móvil', '10/09/21-13/09/21 Mantenimiento defectuoso. Reclamo por garantía.', 'Hidra Serv', true],
        ] as [$fecha, $comp, $trabajo, $coment, $p, $garantia]) {
            Bitacora::create([
                'maquina_id' => $iny->id, 'fecha' => $fecha, 'tipo' => 'correctivo', 'componente' => $comp,
                'trabajo_realizado' => $trabajo, 'responsable_id' => $u['amantto']->id,
                'proveedor_id' => $p ? $prov[$p]->id : null, 'garantia' => $garantia, 'comentarios' => $coment,
            ]);
        }
        Bitacora::create([
            'maquina_id' => $iny->id, 'fecha' => '2021-08-23', 'tipo' => 'preventivo', 'componente' => 'Prensa',
            'trabajo_realizado' => 'Lubricación de prensa', 'responsable_nombre' => 'Angel García',
        ]);
        Bitacora::create([
            'maquina_id' => $fa->id, 'fecha' => '2022-08-18', 'tipo' => 'correctivo', 'componente' => 'Motor y bomba',
            'trabajo_realizado' => 'Cambio de cojinetes, reparación de eje de motor y bomba, cambio de capacitor y cambio de ventilador',
            'responsable_id' => $u['selvin']->id,
        ]);
        Bitacora::create([
            'maquina_id' => $fa->id, 'fecha' => '2023-03-06', 'tipo' => 'mejora', 'componente' => 'Motor',
            'trabajo_realizado' => 'Cambio de retenedor e instalación de nuevo horómetro', 'responsable_id' => $u['selvin']->id,
        ]);

        // ── Productos e inventario inicial ──────────────────────────────────
        $cat = fn (string $n) => CategoriaProducto::where('nombre', $n)->value('id');
        $uni = fn (string $n) => Unidad::where('nombre', $n)->value('id');
        $bod = fn (string $c) => Bodega::where('codigo', $c)->value('id');

        $prod = [];
        foreach ([
            ['MP-TUB-12', 'Tubo PVC eléctrico 1/2" x 10 ft', 'materia_prima', 'Tubería PVC', '1/2"', 'Tubo', 200, [['Manojo', 40]]],
            ['MP-TUB-34', 'Tubo PVC eléctrico 3/4" x 10 ft', 'materia_prima', 'Tubería PVC', '3/4"', 'Tubo', 200, [['Manojo', 32]]],
            ['MP-TUB-1', 'Tubo PVC eléctrico 1" x 10 ft', 'materia_prima', 'Tubería PVC', '1"', 'Tubo', 100, [['Manojo', 25]]],
            ['MP-RES-PVC', 'Resina de PVC', 'materia_prima', 'Resina y aditivos', null, 'Kilogramo', 500, [['Saco 25 kg', 25]]],
            ['PT-COP-12', 'Copla PVC 1/2"', 'producto_terminado', 'Coplas', '1/2"', 'Pieza', 3000, [['Bolsa x 300', 300], ['Caja x 1200', 1200]]],
            ['PT-COP-34', 'Copla PVC 3/4"', 'producto_terminado', 'Coplas', '3/4"', 'Pieza', 3000, [['Bolsa x 300', 300], ['Caja x 1200', 1200]]],
            ['PT-COP-1', 'Copla PVC 1"', 'producto_terminado', 'Coplas', '1"', 'Pieza', 1500, [['Bolsa x 200', 200]]],
            ['PT-COD-12', 'Codo / vuelta PVC 1/2"', 'producto_terminado', 'Codos / vueltas', '1/2"', 'Pieza', 2000, [['Bolsa x 100', 100]]],
            ['PT-COD-34', 'Codo / vuelta PVC 3/4"', 'producto_terminado', 'Codos / vueltas', '3/4"', 'Pieza', 2000, [['Bolsa x 100', 100]]],
            ['REP-RES-2350', 'Resistencia de banda 240-480V 2350W ø150x138mm', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 2, []],
            ['REP-RES-675', 'Resistencia de banda 240-480V 675W ø150x33mm', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 1, []],
            ['REP-RES-250', 'Resistencia de banda 110V 250W ø39x60mm', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 1, []],
            ['REP-ORING-KIT', 'Kit de O-rings bloque electroválvula', 'repuesto', 'Repuestos hidráulicos', null, 'Unidad', 2, []],
            ['REP-FILT-ABF', 'Filtro respiradero Schroeder ABF-3/10', 'repuesto', 'Repuestos hidráulicos', null, 'Unidad', 2, []],
            ['INS-ACE-HID', 'Aceite hidráulico ISO 68', 'insumo', 'Lubricantes', null, 'Galón', 10, [['Cubeta 5 gal', 5]]],
        ] as [$cod, $nom, $tipo, $c, $med, $unidad, $min, $pres]) {
            $prod[$cod] = Producto::create([
                'codigo' => $cod, 'nombre' => $nom, 'tipo' => $tipo, 'categoria_id' => $cat($c),
                'medida' => $med, 'unidad_id' => $uni($unidad), 'stock_minimo' => $min,
            ]);
            foreach ($pres as [$pn, $f]) {
                $prod[$cod]->presentaciones()->create(['nombre' => $pn, 'factor' => $f]);
            }
        }
        // Resistencias ligadas a la lista de la Inyectora 1.
        $iny->partes()->createMany([
            ['grupo' => 'Resistencias', 'especificacion' => '240-480V 2350W', 'dimensiones' => 'ø 150 mm x 138 mm largo', 'cantidad' => 6, 'producto_id' => $prod['REP-RES-2350']->id],
            ['grupo' => 'Resistencias', 'especificacion' => '240-480V 675W', 'dimensiones' => 'ø 150 mm x 33 mm largo', 'cantidad' => 1, 'producto_id' => $prod['REP-RES-675']->id],
            ['grupo' => 'Resistencias', 'especificacion' => '110V 250W', 'dimensiones' => 'ø 39 mm x 60 mm largo', 'cantidad' => 1, 'producto_id' => $prod['REP-RES-250']->id],
        ]);
        $prov['Eléctrica Industrial Guatemala']->productos()->attach($prod['REP-RES-2350']->id, ['precio' => 650, 'dias_entrega' => 8]);
        $prov['Tubería y Plásticos S.A.']->productos()->attach($prod['MP-TUB-34']->id, ['precio' => 18.5, 'dias_entrega' => 3]);

        $inv = app(InventarioService::class);
        $pres = fn (string $cod, string $n) => $prod[$cod]->presentaciones()->where('nombre', $n)->value('id');
        $inv->registrar(['tipo' => 'entrada_compra', 'fecha' => today()->subDays(20), 'bodega_destino_id' => $bod('MP'),
            'proveedor_id' => $prov['Tubería y Plásticos S.A.']->id, 'documento' => 'FAC-10482'], [
                ['producto_id' => $prod['MP-TUB-12']->id, 'presentacion_id' => $pres('MP-TUB-12', 'Manojo'), 'cantidad' => 30, 'costo_unitario' => 460],
                ['producto_id' => $prod['MP-TUB-34']->id, 'presentacion_id' => $pres('MP-TUB-34', 'Manojo'), 'cantidad' => 25, 'costo_unitario' => 592],
                ['producto_id' => $prod['MP-TUB-1']->id, 'presentacion_id' => $pres('MP-TUB-1', 'Manojo'), 'cantidad' => 8, 'costo_unitario' => 700],
                ['producto_id' => $prod['MP-RES-PVC']->id, 'presentacion_id' => $pres('MP-RES-PVC', 'Saco 25 kg'), 'cantidad' => 40, 'costo_unitario' => 312.5],
            ], $u['abodega']);
        $inv->registrar(['tipo' => 'entrada_compra', 'fecha' => today()->subDays(18), 'bodega_destino_id' => $bod('REP'),
            'proveedor_id' => $prov['Eléctrica Industrial Guatemala']->id, 'documento' => 'FAC-2231'], [
                ['producto_id' => $prod['REP-RES-2350']->id, 'cantidad' => 4, 'costo_unitario' => 650],
                ['producto_id' => $prod['REP-RES-675']->id, 'cantidad' => 2, 'costo_unitario' => 420],
                ['producto_id' => $prod['REP-RES-250']->id, 'cantidad' => 2, 'costo_unitario' => 180],
                ['producto_id' => $prod['REP-ORING-KIT']->id, 'cantidad' => 1, 'costo_unitario' => 350],
                ['producto_id' => $prod['REP-FILT-ABF']->id, 'cantidad' => 3, 'costo_unitario' => 275],
                ['producto_id' => $prod['INS-ACE-HID']->id, 'presentacion_id' => $pres('INS-ACE-HID', 'Cubeta 5 gal'), 'cantidad' => 4, 'costo_unitario' => 610],
            ], $u['abodega']);
        $inv->registrar(['tipo' => 'salida_produccion', 'fecha' => today()->subDays(10), 'bodega_origen_id' => $bod('MP'),
            'maquina_id' => $maq['ENC2']->id, 'referencia' => 'Operador: línea de coplas'], [
                ['producto_id' => $prod['MP-TUB-34']->id, 'presentacion_id' => $pres('MP-TUB-34', 'Manojo'), 'cantidad' => 6],
                ['producto_id' => $prod['MP-TUB-12']->id, 'presentacion_id' => $pres('MP-TUB-12', 'Manojo'), 'cantidad' => 4],
            ], $u['auxbodega']);
        $inv->registrar(['tipo' => 'ingreso_produccion', 'fecha' => today()->subDays(8), 'bodega_destino_id' => $bod('PT'),
            'referencia' => 'Mesas de conteo · turno A'], [
                ['producto_id' => $prod['PT-COP-34']->id, 'presentacion_id' => $pres('PT-COP-34', 'Bolsa x 300'), 'cantidad' => 18],
                ['producto_id' => $prod['PT-COP-12']->id, 'presentacion_id' => $pres('PT-COP-12', 'Bolsa x 300'), 'cantidad' => 12],
                ['producto_id' => $prod['PT-COD-34']->id, 'presentacion_id' => $pres('PT-COD-34', 'Bolsa x 100'), 'cantidad' => 14],
            ], $u['auxbodega']);
        $inv->registrar(['tipo' => 'salida_despacho', 'fecha' => today()->subDays(3), 'bodega_origen_id' => $bod('PT'),
            'documento' => 'ENV-5531', 'referencia' => 'Cliente: Ferretería El Martillo'], [
                ['producto_id' => $prod['PT-COP-34']->id, 'presentacion_id' => $pres('PT-COP-34', 'Bolsa x 300'), 'cantidad' => 10],
            ], $u['abodega']);
        // Ajuste que registra el auxiliar: queda pendiente de aprobación.
        $inv->registrar(['tipo' => 'ajuste_salida', 'fecha' => today()->subDay(), 'bodega_origen_id' => $bod('PT'),
            'notas' => 'Bolsa dañada por humedad en el área de empaque'], [
                ['producto_id' => $prod['PT-COP-12']->id, 'cantidad' => 45],
            ], $u['auxbodega']);

        // ── Órdenes de trabajo (hoja "Data" del libro de tareas) ────────────
        $ots = app(OrdenTrabajoService::class);
        $crear = function (array $d, string $resp, string $estado, int $progreso, ?string $nota) use ($ots, $u) {
            $ot = $ots->crear($d + ['responsable_id' => $u[$resp]->id], $u['amantto']);
            if ($estado !== 'pendiente' || $progreso > 0) {
                $ots->registrarSeguimiento($ot, [
                    'estado' => $estado, 'progreso' => $progreso, 'texto' => $nota,
                    'motivo_espera' => $estado === 'en_espera' ? $nota : null, 'horas' => $progreso > 0 ? 2 : null,
                ], $u[$resp]);
            }

            return $ot;
        };
        $crear(['titulo' => 'Revisión y limpieza de válvulas del sistema de cierre', 'maquina_id' => $maq['INY4']->id,
            'tipo' => 'correctivo', 'especialidad_id' => $esp('Eléctrico'), 'prioridad' => 'alta',
            'fecha_inicio' => today()->subDays(20), 'fecha_vencimiento' => today()->subDays(4)], 'kenneth', 'en_progreso', 0, null);
        $crear(['titulo' => 'Instalación de transformador 220V', 'tipo' => 'proyecto', 'especialidad_id' => $esp('Eléctrico'),
            'prioridad' => 'alta', 'fecha_inicio' => today()->subDays(25), 'fecha_vencimiento' => today()->subDays(4)],
            'miguel', 'en_espera', 50, 'Se necesitan 2 cotizaciones: una de 50 KVA y una de 35 KVA.');
        $crear(['titulo' => 'Reparación de iluminación de planta', 'tipo' => 'correctivo', 'especialidad_id' => $esp('Eléctrico'),
            'prioridad' => 'alta', 'fecha_inicio' => today()->subDays(24), 'fecha_vencimiento' => today()->addDays(6)],
            'selvin', 'en_progreso', 40, 'Se cambiaron las lámparas del pasillo principal.');
        $crear(['titulo' => 'Ajuste de sensores de la contadora automática', 'maquina_id' => $maq['MCA']->id,
            'tipo' => 'correctivo', 'especialidad_id' => $esp('Mecánico'), 'prioridad' => 'alta',
            'fecha_inicio' => today()->subDays(24), 'fecha_vencimiento' => today()->addDays(6)], 'selvin', 'en_progreso', 20, null);
        $crear(['titulo' => 'Instalación de cisterna de entrada a fábrica', 'tipo' => 'proyecto',
            'especialidad_id' => $esp('Servicios generales'), 'prioridad' => 'media',
            'fecha_inicio' => today()->subDays(24), 'fecha_vencimiento' => today()->addDays(37)],
            'selvin', 'en_progreso', 20, 'Se está viendo el momento para la instalación.');
        $crear(['titulo' => 'Fabricación de guardas para encampanadoras x 3', 'maquina_id' => $maq['ENC1']->id,
            'tipo' => 'mejora', 'especialidad_id' => $esp('Soldadura'), 'prioridad' => 'alta',
            'fecha_inicio' => today(), 'fecha_vencimiento' => today()->addDays(15)], 'canche', 'en_progreso', 10, 'Se tiene material para hacer el trabajo.');
        $crear(['titulo' => 'Moldes de curvado 3/4" x 2', 'maquina_id' => $maq['ENC2']->id, 'tipo' => 'mejora',
            'especialidad_id' => $esp('Soldadura'), 'prioridad' => 'alta',
            'fecha_inicio' => today()->subDays(2), 'fecha_vencimiento' => today()->addDays(2)],
            'canche', 'en_progreso', 50, 'Se terminó el primero para las pruebas; monitorear y corregir.');
        $crear(['titulo' => 'Tratamiento de agua de termoformado', 'tipo' => 'mejora',
            'especialidad_id' => $esp('Hidráulico / Neumático'), 'prioridad' => 'alta',
            'fecha_inicio' => today()->subDays(30), 'fecha_vencimiento' => today()->addDays(11)], 'djose', 'en_progreso', 50, null);
        $crear(['titulo' => 'Fabricar piñón para banda transportadora', 'maquina_id' => $maq['BTF']->id,
            'tipo' => 'correctivo', 'especialidad_id' => $esp('Mecánico'), 'prioridad' => 'alta',
            'fecha_inicio' => today()->subDays(30), 'fecha_vencimiento' => today()->addDays(16)],
            'miguel', 'en_espera', 20, 'Se necesita fabricar piñón; se están tomando medidas.');
        $crear(['titulo' => 'Revisión eléctrica de la conectora automática', 'maquina_id' => $maq['CA1']->id,
            'tipo' => 'correctivo', 'especialidad_id' => $esp('Eléctrico'), 'prioridad' => 'media',
            'fecha_inicio' => today()->subDays(24), 'fecha_vencimiento' => today()->addDays(15)], 'selvin', 'pendiente', 0, null);

        // Una completada, para ver cómo llega a la bitácora.
        $ot = $ots->crear(['titulo' => 'Cambio de filtro respiradero', 'maquina_id' => $iny->id, 'tipo' => 'preventivo',
            'especialidad_id' => $esp('Hidráulico / Neumático'), 'prioridad' => 'media', 'responsable_id' => $u['djose']->id,
            'fecha_inicio' => today()->subDays(6), 'fecha_vencimiento' => today()->subDays(2)], $u['amantto']);
        $ots->registrarSeguimiento($ot, ['estado' => 'en_progreso', 'progreso' => 60, 'texto' => 'Se retiró el filtro anterior.', 'horas' => 1], $u['djose']);
        $ots->completar($ot, ['trabajo_realizado' => 'Se cambió el filtro respiradero Schroeder ABF-3/10 y se revisó el nivel de aceite.',
            'componente' => 'Sistema hidráulico', 'horas' => 0.5, 'horometro' => 48250], $u['djose']);

        // ── Planes preventivos ──────────────────────────────────────────────
        foreach ([
            [$iny, 'Lubricación de prensa', 'semanas', 2, 'Mecánico', 'selvin', ['Limpiar puntos de engrase', 'Engrasar guías y bujes', 'Revisar fugas']],
            [$iny, 'Revisión de resistencias y termocuplas', 'meses', 1, 'Eléctrico', 'kenneth', ['Medir amperaje por zona', 'Revisar conexiones', 'Reemplazar dañadas']],
            [$maq['COMPIR'], 'Mantenimiento de compresor de tornillo', 'meses', 3, 'Mecánico', 'selvin', ['Cambio de filtro de aire', 'Revisión de aceite', 'Purga de condensados']],
            [$maq['ENC1'], 'Limpieza y ajuste de encampanadora', 'semanas', 1, 'Mecánico', 'canche', ['Limpiar moldes', 'Verificar temperatura', 'Ajustar guías']],
            [$maq['BA1'], 'Revisión de bomba de cisterna principal', 'meses', 1, 'Eléctrico', 'miguel', ['Medir consumo', 'Revisar presostato', 'Limpiar pichacha']],
        ] as $i => [$m, $titulo, $unidad, $valor, $e, $resp, $check]) {
            PlanMantenimiento::create([
                'maquina_id' => $m->id, 'titulo' => $titulo, 'checklist' => $check, 'tipo' => 'preventivo',
                'especialidad_id' => $esp($e), 'responsable_id' => $u[$resp]->id, 'prioridad' => 'media',
                'frecuencia_valor' => $valor, 'frecuencia_unidad' => $unidad,
                'proxima_fecha' => today()->addDays([1, 5, 12, 3, 20][$i]), 'dias_anticipacion' => 3, 'duracion_estimada' => 2,
            ]);
        }
        $ots->generarPreventivas($admin);

        // ── Herramientas ────────────────────────────────────────────────────
        foreach ([
            ['HER-001', 'Multímetro digital', 'Medición', 'Fluke', 'kenneth'],
            ['HER-002', 'Pinza amperimétrica', 'Medición', 'Fluke', 'miguel'],
            ['HER-003', 'Juego de llaves combinadas 8-24 mm', 'Manual', 'Stanley', 'selvin'],
            ['HER-004', 'Taladro percutor 1/2"', 'Eléctrica', 'DeWalt', 'selvin'],
            ['HER-005', 'Soldadora MIG 220V', 'Soldadura', 'Lincoln', 'canche'],
            ['HER-006', 'Juego de dados 1/2"', 'Manual', 'Truper', null],
            ['HER-007', 'Esmeriladora angular 4 1/2"', 'Eléctrica', 'Makita', null],
            ['HER-008', 'Torquímetro 10-150 lb-ft', 'Manual', 'Truper', 'djose'],
        ] as [$cod, $nom, $cat2, $marca, $resp]) {
            $h = Herramienta::create(['codigo' => $cod, 'nombre' => $nom, 'categoria' => $cat2, 'marca' => $marca,
                'estado' => $resp ? 'asignada' : 'disponible', 'asignada_a' => $resp ? $u[$resp]->id : null]);
            if ($resp) {
                $h->asignaciones()->create(['user_id' => $u[$resp]->id, 'entregado_por' => $u['amantto']->id,
                    'entregado_at' => now()->subDays(30), 'estado_entrega' => 'bueno']);
            }
        }
    }
}
