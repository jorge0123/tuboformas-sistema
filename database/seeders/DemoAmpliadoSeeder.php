<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Bitacora;
use App\Models\CategoriaProducto;
use App\Models\Especialidad;
use App\Models\Herramienta;
use App\Models\Maquina;
use App\Models\PlanMantenimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\OrdenTrabajoService;
use Illuminate\Database\Seeder;

/**
 * Más datos de ejemplo sobre DemoSeeder para ver el sistema "lleno": máquinas nuevas y fichas
 * técnicas con repuestos, bodega de repuestos completa, proveedores, meses de movimientos de
 * repuestos, OT en todos los estados, bitácora histórica, planes y herramientas.
 *
 *   php artisan db:seed --class=DemoAmpliadoSeeder
 *
 * Se puede correr una sola vez (si ya existe REP-ROD-6205 no hace nada).
 */
class DemoAmpliadoSeeder extends Seeder
{
    private array $u = [];

    private array $maq = [];

    private array $prod = [];

    private array $prov = [];

    public function run(): void
    {
        if (! Maquina::exists()) {
            $this->call(DemoSeeder::class);
        }
        if (Producto::where('codigo', 'REP-ROD-6205')->exists()) {
            $this->command?->info('DemoAmpliadoSeeder ya se había cargado.');

            return;
        }
        config(['mail.default' => 'log']);

        $this->u = User::all()->keyBy('username')->all();
        $this->maquinas();
        $this->productos();
        $this->proveedores();
        $this->fichasTecnicas();
        $this->movimientos();
        $this->ordenes();
        $this->bitacoraHistorica();
        $this->planes();
        $this->herramientas();
    }

    private function esp(string $n): ?int
    {
        return Especialidad::where('nombre', $n)->value('id');
    }

    // ── Máquinas nuevas ─────────────────────────────────────────────────
    private function maquinas(): void
    {
        $area = fn (string $n) => Area::where('nombre', $n)->value('id');
        foreach ([
            [31, 'Extrusión', 'EXT1', 'Extrusora de tubería 1', 'Kraussmaffei', 'KMD 60', 'A', 2014, 32150],
            [32, 'Extrusión', 'EXT2', 'Extrusora de tubería 2', 'Bausano', 'MD 72/30', 'A', 2018, 18430],
            [33, 'Extrusión', 'MOL1', 'Molino triturador', 'Rapid', 'Serie 300', 'B', 2011, null],
            [34, 'Extrusión', 'MEZ1', 'Mezcladora de resina', 'Papenmeier', 'TSHK 200', 'A', 2009, 25400],
            [35, 'Servicios', 'TOR1', 'Torre de enfriamiento', 'Evapco', 'LSTE 5', 'B', 2016, null],
            [36, 'Servicios', 'PLE1', 'Planta eléctrica de emergencia', 'Cummins', 'C150 D6', 'A', 2019, 820],
            [37, 'Bodega', 'MON1', 'Montacargas', 'Toyota', '8FGU25', 'B', 2017, 9150],
            [38, 'Empaque', 'SEL1', 'Selladora de bolsas', 'Hualian', 'FR-900', 'C', 2021, null],
        ] as [$n, $a, $cod, $nombre, $marca, $modelo, $crit, $anio, $horas]) {
            Maquina::create(['numero' => $n, 'area_id' => $area($a), 'codigo' => $cod, 'nombre' => $nombre, 'marca' => $marca,
                'modelo' => $modelo, 'criticidad' => $crit, 'anio' => $anio, 'horometro' => $horas, 'estado' => 'operativa',
                'ubicacion' => $a === 'Servicios' ? 'Patio de servicios' : "Nave de $a"]);
        }
        $this->maq = Maquina::all()->keyBy('codigo')->all();
    }

    // ── Catálogo de repuestos ───────────────────────────────────────────
    private function productos(): void
    {
        foreach (['Rodamientos y transmisión', 'Sensores y control', 'Seguridad industrial'] as $c) {
            CategoriaProducto::firstOrCreate(['nombre' => $c]);
        }
        $cat = fn (string $n) => CategoriaProducto::where('nombre', $n)->value('id');
        $uni = fn (string $n) => Unidad::where('nombre', $n)->value('id');

        $filas = [
            // Materia prima
            // Producto terminado: variantes naranja de los que ya existen
            // Producto terminado nuevo
            // Repuestos
            ['REP-ROD-6205', 'Rodamiento 6205-2RS', 'repuesto', 'Rodamientos y transmisión', null, 'Unidad', 4],
            ['REP-ROD-6304', 'Rodamiento 6304-ZZ', 'repuesto', 'Rodamientos y transmisión', null, 'Unidad', 2],
            ['REP-ROD-22212', 'Rodamiento de rodillos 22212 E', 'repuesto', 'Rodamientos y transmisión', null, 'Unidad', 1],
            ['REP-BANDA-A38', 'Banda en V A-38', 'repuesto', 'Rodamientos y transmisión', null, 'Unidad', 4],
            ['REP-BANDA-B52', 'Banda en V B-52', 'repuesto', 'Rodamientos y transmisión', null, 'Unidad', 3],
            ['REP-CAD-40', 'Cadena de rodillos ANSI 40 (tramo 3 m)', 'repuesto', 'Rodamientos y transmisión', null, 'Unidad', 1],
            ['REP-RES-500', 'Resistencia de banda 220V 500W ø60x50mm', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 4],
            ['REP-RES-EXT', 'Resistencia cerámica extrusora 220V 1500W', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 3],
            ['REP-TERMO-K', 'Termocupla tipo K con bayoneta', 'repuesto', 'Sensores y control', null, 'Unidad', 4],
            ['REP-SEN-IND', 'Sensor inductivo M18 PNP NA', 'repuesto', 'Sensores y control', null, 'Unidad', 2],
            ['REP-SEN-FOTO', 'Sensor fotoeléctrico difuso 24V', 'repuesto', 'Sensores y control', null, 'Unidad', 2],
            ['REP-PID', 'Controlador de temperatura PID 48x48', 'repuesto', 'Sensores y control', null, 'Unidad', 1],
            ['REP-CONT-25', 'Contactor 3P 25A bobina 220V', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 2],
            ['REP-BRK-2X30', 'Breaker termomagnético 2x30A', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 2],
            ['REP-FUS-15', 'Fusible cilíndrico 10x38 15A', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 10],
            ['REP-CAP-ARR', 'Capacitor de arranque 189-227 µF 250V', 'repuesto', 'Repuestos eléctricos', null, 'Unidad', 2],
            ['REP-SELLO-34', 'Sello mecánico 3/4" para bomba', 'repuesto', 'Repuestos mecánicos', null, 'Unidad', 1],
            ['REP-CUCH-CT', 'Cuchilla para cortador de tubo PVC', 'repuesto', 'Repuestos mecánicos', null, 'Unidad', 2],
            ['REP-CUCH-MOL', 'Juego de cuchillas para molino', 'repuesto', 'Repuestos mecánicos', null, 'Unidad', 1],
            ['REP-BOQ-INY', 'Boquilla de inyección 3 mm', 'repuesto', 'Repuestos mecánicos', null, 'Unidad', 2],
            ['REP-FILT-AIRE', 'Filtro de aire compresor de tornillo', 'repuesto', 'Repuestos hidráulicos', null, 'Unidad', 2],
            ['REP-FILT-ACE', 'Filtro de aceite compresor de tornillo', 'repuesto', 'Repuestos hidráulicos', null, 'Unidad', 2],
            ['REP-SEP-ACE', 'Separador aire-aceite compresor', 'repuesto', 'Repuestos hidráulicos', null, 'Unidad', 1],
            ['REP-FILT-DSL', 'Filtro de combustible planta eléctrica', 'repuesto', 'Repuestos hidráulicos', null, 'Unidad', 2],
            ['REP-MANG-12', 'Manguera hidráulica 1/2" 2 hilos (m)', 'repuesto', 'Repuestos hidráulicos', null, 'Metro', 5],
            // Insumos
            ['INS-ACE-46', 'Aceite hidráulico ISO 46', 'insumo', 'Lubricantes', null, 'Galón', 10],
            ['INS-ACE-COMP', 'Aceite para compresor sintético', 'insumo', 'Lubricantes', null, 'Galón', 4],
            ['INS-GRASA', 'Grasa multiuso EP2', 'insumo', 'Lubricantes', null, 'Libra', 20],
            ['INS-WD40', 'Lubricante penetrante en aerosol', 'insumo', 'Consumibles', null, 'Unidad', 6],
            ['INS-CEMENTO', 'Cemento solvente para PVC', 'insumo', 'Consumibles', null, 'Litro', 4],
            ['INS-THINNER', 'Thinner', 'insumo', 'Consumibles', null, 'Galón', 3],
            ['INS-TRAPO', 'Trapo industrial', 'insumo', 'Consumibles', null, 'Libra', 20],
            ['INS-ELEC-6013', 'Electrodo 6013 1/8"', 'insumo', 'Consumibles', null, 'Libra', 10],
            ['INS-DISCO', 'Disco de corte metal 4 1/2"', 'insumo', 'Consumibles', null, 'Unidad', 20],
            ['INS-GUANTE', 'Guantes de nitrilo', 'insumo', 'Seguridad industrial', null, 'Caja', 3],
            ['INS-LENTES', 'Lentes de seguridad claros', 'insumo', 'Seguridad industrial', null, 'Unidad', 10],
            ['INS-TAPON', 'Tapones auditivos desechables', 'insumo', 'Seguridad industrial', null, 'Unidad', 100],
        ];
        foreach ($filas as [$cod, $nom, $tipo, $c, $med, $unidad, $min]) {
            $this->prod[$cod] = Producto::create([
                'codigo' => $cod, 'nombre' => $nom, 'tipo' => $tipo, 'categoria_id' => $cat($c), 'medida' => $med,
                'unidad_id' => $uni($unidad), 'stock_minimo' => $min,
                'ubicacion' => ['repuesto' => 'Estante R-'.(crc32($cod) % 9 + 1), 'insumo' => 'Estante I-'.(crc32($cod) % 4 + 1)][$tipo],
            ]);
        }
        // Los de DemoSeeder también, para usarlos en movimientos.
        foreach (Producto::whereNotIn('codigo', array_keys($this->prod))->get() as $p) {
            $this->prod[$p->codigo] = $p;
        }
    }

    // ── Proveedores ─────────────────────────────────────────────────────
    private function proveedores(): void
    {
        foreach ([
            ['Rodamientos y Bandas de Guatemala', '4521877-3', 'Julio Ajú', '2440-1122', 'ventas@rodabandas.com.gt', 'Calzada Roosevelt 22-45 zona 11', ['repuestos'],
                [['REP-ROD-6205', 'SKF-6205-2RS', 48, 1], ['REP-ROD-6304', 'SKF-6304-ZZ', 55, 1], ['REP-ROD-22212', 'SKF-22212E', 1250, 10], ['REP-BANDA-A38', 'GATES-A38', 62, 2], ['REP-BANDA-B52', 'GATES-B52', 95, 2], ['REP-CAD-40', 'ANSI40-3M', 310, 5]]],
            ['Electro Industrial Centroamericana', '7788120-K', 'Sandra Pineda', '2360-8899', 'cotizaciones@eicsa.com.gt', '5a. avenida 10-15 zona 9', ['repuestos'],
                [['REP-CONT-25', 'LC1D25M7', 385, 3], ['REP-BRK-2X30', 'EZ9-2P30', 140, 1], ['REP-FUS-15', 'FUS1038-15', 12, 1], ['REP-TERMO-K', 'TK-BAY-6', 175, 4], ['REP-SEN-IND', 'IME18-08', 420, 7], ['REP-SEN-FOTO', 'WL100-2', 560, 7], ['REP-PID', 'E5CC-RX2', 1150, 10], ['REP-RES-500', 'RB60-500', 210, 8], ['REP-RES-EXT', 'RC-1500', 640, 12]]],
            ['Lubricantes y Filtros Express', '6612099-1', 'Óscar Leiva', '2485-2200', 'oscar@lubrifiltros.gt', 'Villa Nueva', ['insumos', 'repuestos'],
                [['INS-ACE-46', 'ISO46-5G', 520, 1], ['INS-ACE-COMP', 'SYN-COMP', 890, 3], ['INS-GRASA', 'EP2-35', 690, 1], ['REP-FILT-AIRE', 'IR-39903265', 480, 5], ['REP-FILT-ACE', 'IR-39911631', 365, 5], ['REP-SEP-ACE', 'IR-54625097', 1850, 12], ['REP-FILT-DSL', 'FF5052', 150, 2]]],
            ['Ferretería El Tornillo Industrial', '1209987-6', 'Luis Castro', '2230-7788', 'mostrador@eltornillo.gt', '18 calle 5-30 zona 1', ['insumos', 'repuestos'],
                [['INS-WD40', null, 48, 1], ['INS-CEMENTO', null, 95, 1], ['INS-THINNER', null, 85, 1], ['INS-TRAPO', null, 12, 1], ['INS-ELEC-6013', null, 22, 1], ['INS-DISCO', null, 14, 1], ['INS-GUANTE', null, 115, 1], ['INS-LENTES', null, 18, 1], ['INS-TAPON', null, 1.2, 1], ['REP-SELLO-34', null, 210, 3], ['REP-CAP-ARR', null, 145, 2], ['REP-MANG-12', null, 68, 1]]],
            ['Taller Mecánico Precisión', '5540012-2', 'Rodrigo Chacón', '5520-3344', 'precision.taller@gmail.com', 'Mixco', ['servicios', 'repuestos'],
                [['REP-CUCH-CT', null, 350, 6], ['REP-CUCH-MOL', null, 2400, 15], ['REP-BOQ-INY', null, 480, 10]]],
            ['Refrigeración Industrial Maya', '8877001-5', 'Ingrid Sosa', '2477-6060', 'servicio@frimaya.com', 'Zona 12', ['servicios'], []],
        ] as [$nombre, $nit, $contacto, $tel, $email, $dir, $tipos, $catalogo]) {
            $p = Proveedor::create(['nombre' => $nombre, 'nit' => $nit, 'contacto' => $contacto, 'telefono' => $tel, 'email' => $email, 'direccion' => $dir, 'tipos' => $tipos]);
            foreach ($catalogo as [$cod, $codProv, $precio, $dias]) {
                $p->productos()->attach($this->prod[$cod]->id, ['codigo_proveedor' => $codProv, 'precio' => $precio, 'dias_entrega' => $dias]);
            }
            $this->prov[$nombre] = $p;
        }
        foreach (Proveedor::whereNotIn('nombre', array_keys($this->prov))->get() as $p) {
            $this->prov[$p->nombre] = $p;
        }
    }

    // ── Fichas técnicas y repuestos de cada máquina ─────────────────────
    private function fichasTecnicas(): void
    {
        $fichas = [
            'EXT1' => [
                ['Extrusora', ['Tornillo' => 'Doble cónico 60/125', 'Capacidad' => '350 kg/h', 'Zonas de calefacción' => '4 + 2 en cabezal', 'Voltaje' => '460 Vac']],
                ['Motor principal', ['Marca' => 'Siemens', 'Potencia' => '55 kW', 'RPM' => '1780', 'Variador' => 'ABB ACS580']],
                ['Tina de enfriamiento', ['Largo' => '6 m', 'Vacío' => '-0.6 bar', 'Bomba' => '3 HP']],
            ],
            'EXT2' => [
                ['Extrusora', ['Tornillo' => 'Doble paralelo 72', 'Capacidad' => '450 kg/h', 'Zonas de calefacción' => '5', 'Voltaje' => '460 Vac']],
                ['Halador', ['Tipo' => 'Orugas', 'Velocidad' => '0.5–15 m/min', 'Motor' => '3 HP']],
            ],
            'MEZ1' => [['Mezcladora', ['Tipo' => 'Caliente/fría', 'Capacidad' => '200 L', 'Motor' => '75 HP', 'Temperatura descarga' => '120 °C']]],
            'MOL1' => [['Molino', ['Capacidad' => '150 kg/h', 'Motor' => '15 HP', 'Criba' => '8 mm', 'Cuchillas' => '3 rotativas + 2 fijas']]],
            'CA1' => [['Motor principal', ['Marca' => 'CROWN', 'Potencia' => '3 HP', 'Voltaje' => '220 V', 'Frecuencia' => '50 Hz', 'Corriente' => '12 A']],
                ['Sistema neumático', ['Marca' => 'SMC', 'Presión de trabajo' => '90 psi', 'Electroválvulas' => '4 × 5/2 24 VDC']]],
            'BTF' => [['Motorreductor', ['Marca' => 'SEW', 'Potencia' => '5 HP', 'Relación' => '1:40', 'RPM salida' => '45']]],
            'ENC1' => [['Calefacción', ['Resistencias' => '6 × 500 W', 'Control' => 'PID', 'Rango' => '180–220 °C']], ['Neumática', ['Cilindros' => '2 × ø50 mm', 'Presión' => '80 psi']]],
            'ENC2' => [['Calefacción', ['Resistencias' => '6 × 500 W', 'Control' => 'PID', 'Rango' => '180–220 °C']]],
            'ENC3' => [['Calefacción', ['Resistencias' => '6 × 500 W', 'Control' => 'PID', 'Rango' => '180–220 °C']]],
            'COMPIR' => [['Unidad compresora', ['Marca' => 'Ingersoll Rand', 'Modelo' => 'UP6-30', 'Potencia' => '30 HP', 'Presión máx.' => '128 psi', 'Caudal' => '125 CFM']],
                ['Motor', ['Voltaje' => '220 Vac trifásico', 'Corriente' => '45 A', 'Horas cambio de aceite' => '4000']]],
            'BA1' => [['Bomba centrífuga', ['Marca' => 'STA-RITE', 'Potencia' => '3/4 HP', 'Voltaje' => '220 V', 'Corriente' => '6.2 A', 'Succión' => '1 1/4"']]],
            'MCA' => [['Sistema de conteo', ['Sensor' => 'Fotoeléctrico difuso', 'Velocidad' => '300 pzas/min', 'PLC' => 'Delta DVP-14SS']]],
            'PLE1' => [['Motor diésel', ['Marca' => 'Cummins', 'Modelo' => '6BTA5.9', 'Potencia' => '150 kVA', 'Tanque' => '300 L']], ['Transferencia', ['Tipo' => 'Automática ATS', 'Tiempo de arranque' => '10 s']]],
            'MON1' => [['Motor', ['Combustible' => 'Gas LP', 'Capacidad' => '5000 lb', 'Altura de elevación' => '4.7 m']]],
            'INY2' => [['Inyectora', ['Fuerza de cierre' => '150 t', 'Shot size' => '9 oz', 'Voltaje' => '460 Vac']]],
            'FR1' => [['Husillo', ['Cono' => 'ISO 40', 'RPM máx.' => '2500', 'Motor' => '5 HP']]],
        ];
        foreach ($fichas as $cod => $componentes) {
            foreach ($componentes as $i => [$nombre, $specs]) {
                $this->maq[$cod]->componentes()->create(['nombre' => $nombre, 'orden' => $i + 1,
                    'especificaciones' => collect($specs)->map(fn ($v, $k) => ['clave' => $k, 'valor' => $v])->values()->all()]);
            }
        }

        $partes = [
            'EXT1' => [['Resistencias', 'Cerámica 220V 1500W', 'ø 120 × 80 mm', 6, 'REP-RES-EXT'], ['Sensores', 'Termocupla tipo K', 'Bayoneta M12', 6, 'REP-TERMO-K'], ['Control', 'Controlador PID', '48 × 48 mm', 6, 'REP-PID'], ['Transmisión', 'Banda en V', 'B-52', 3, 'REP-BANDA-B52']],
            'EXT2' => [['Resistencias', 'Cerámica 220V 1500W', 'ø 120 × 80 mm', 5, 'REP-RES-EXT'], ['Sensores', 'Termocupla tipo K', 'Bayoneta M12', 5, 'REP-TERMO-K'], ['Rodamientos', 'Rodamiento de rodillos', '22212 E', 2, 'REP-ROD-22212']],
            'MOL1' => [['Cuchillas', 'Juego de cuchillas', '3 rotativas + 2 fijas', 1, 'REP-CUCH-MOL'], ['Transmisión', 'Banda en V', 'B-52', 2, 'REP-BANDA-B52']],
            'MEZ1' => [['Rodamientos', 'Rodamiento de rodillos', '22212 E', 2, 'REP-ROD-22212'], ['Eléctrico', 'Contactor 3P 25A', 'Bobina 220V', 2, 'REP-CONT-25']],
            'CA1' => [['Eléctrico', 'Fusible 15A', '10 × 38 mm', 3, 'REP-FUS-15'], ['Sensores', 'Sensor inductivo', 'M18 PNP', 2, 'REP-SEN-IND']],
            'BTF' => [['Rodamientos', 'Rodamiento eje principal', '6205-2RS', 2, 'REP-ROD-6205'], ['Transmisión', 'Cadena de rodillos', 'ANSI 40', 1, 'REP-CAD-40']],
            'ENC1' => [['Resistencias', 'Resistencia de banda 500W', 'ø 60 × 50 mm', 6, 'REP-RES-500'], ['Sensores', 'Termocupla tipo K', 'Bayoneta', 2, 'REP-TERMO-K']],
            'ENC2' => [['Resistencias', 'Resistencia de banda 500W', 'ø 60 × 50 mm', 6, 'REP-RES-500'], ['Sensores', 'Termocupla tipo K', 'Bayoneta', 2, 'REP-TERMO-K']],
            'ENC3' => [['Resistencias', 'Resistencia de banda 500W', 'ø 60 × 50 mm', 6, 'REP-RES-500'], ['Sensores', 'Termocupla tipo K', 'Bayoneta', 2, 'REP-TERMO-K']],
            'COMPIR' => [['Filtros', 'Filtro de aire', 'IR 39903265', 1, 'REP-FILT-AIRE'], ['Filtros', 'Filtro de aceite', 'IR 39911631', 1, 'REP-FILT-ACE'], ['Filtros', 'Separador aire-aceite', 'IR 54625097', 1, 'REP-SEP-ACE'], ['Lubricación', 'Aceite sintético', 'Cambio cada 4000 h', 5, 'INS-ACE-COMP']],
            'COMPHZ' => [['Filtros', 'Filtro de aire', 'Genérico', 1, 'REP-FILT-AIRE'], ['Filtros', 'Filtro de aceite', 'Genérico', 1, 'REP-FILT-ACE']],
            'BA1' => [['Bomba', 'Sello mecánico', '3/4"', 1, 'REP-SELLO-34'], ['Eléctrico', 'Capacitor de arranque', '189-227 µF', 1, 'REP-CAP-ARR']],
            'BA2' => [['Bomba', 'Sello mecánico', '3/4"', 1, 'REP-SELLO-34']],
            'MCA' => [['Sensores', 'Sensor fotoeléctrico', 'Difuso 24V', 2, 'REP-SEN-FOTO'], ['Eléctrico', 'Fusible 15A', '10 × 38 mm', 2, 'REP-FUS-15']],
            'CT1' => [['Corte', 'Cuchilla para tubo PVC', 'ø 250 mm', 1, 'REP-CUCH-CT']],
            'INY2' => [['Inyección', 'Boquilla', '3 mm', 1, 'REP-BOQ-INY'], ['Sensores', 'Termocupla tipo K', 'Bayoneta', 4, 'REP-TERMO-K']],
            'INY3' => [['Inyección', 'Boquilla', '3 mm', 1, 'REP-BOQ-INY'], ['Hidráulico', 'Aceite hidráulico', 'ISO 46', 40, 'INS-ACE-46']],
            'PLE1' => [['Filtros', 'Filtro de combustible', 'FF5052', 2, 'REP-FILT-DSL']],
            'FR1' => [['Transmisión', 'Banda en V', 'A-38', 2, 'REP-BANDA-A38'], ['Rodamientos', 'Rodamiento de husillo', '6304-ZZ', 2, 'REP-ROD-6304']],
            'TOR1' => [['Motor ventilador', 'Rodamiento', '6205-2RS', 2, 'REP-ROD-6205'], ['Transmisión', 'Banda en V', 'A-38', 2, 'REP-BANDA-A38']],
        ];
        foreach ($partes as $cod => $filas) {
            foreach ($filas as [$grupo, $esp, $dim, $cant, $prodCod]) {
                $this->maq[$cod]->partes()->create(['grupo' => $grupo, 'especificacion' => $esp, 'dimensiones' => $dim,
                    'cantidad' => $cant, 'producto_id' => $this->prod[$prodCod]->id]);
            }
        }
    }

    // ── Movimientos de repuestos (≈ 2 meses) ────────────────────────────
    private function movimientos(): void
    {
        $inv = app(InventarioService::class);
        $u = $this->u;
        $prov = fn (string $n) => $this->prov[$n]->id;
        $linea = fn (string $cod, float $cant, ?float $costo = null) => array_filter([
            'producto_id' => $this->prod[$cod]->id, 'cantidad' => $cant, 'costo_unitario' => $costo,
        ], fn ($v) => $v !== null);

        // Compras
        $inv->registrar(['tipo' => 'entrada_compra', 'fecha' => today()->subDays(52), 'proveedor_id' => $prov('Rodamientos y Bandas de Guatemala'), 'documento' => 'FAC-B-1182'], [
            $linea('REP-ROD-6205', 10, 48), $linea('REP-ROD-6304', 6, 55), $linea('REP-ROD-22212', 2, 1250),
            $linea('REP-BANDA-A38', 8, 62), $linea('REP-BANDA-B52', 6, 95), $linea('REP-CAD-40', 2, 310),
        ], $u['amantto']);
        $inv->registrar(['tipo' => 'entrada_compra', 'fecha' => today()->subDays(50), 'proveedor_id' => $prov('Electro Industrial Centroamericana'), 'documento' => 'FAC-EIC-5540'], [
            $linea('REP-CONT-25', 4, 385), $linea('REP-BRK-2X30', 4, 140), $linea('REP-FUS-15', 30, 12),
            $linea('REP-TERMO-K', 10, 175), $linea('REP-SEN-IND', 3, 420), $linea('REP-SEN-FOTO', 3, 560),
            $linea('REP-PID', 2, 1150), $linea('REP-RES-500', 12, 210), $linea('REP-RES-EXT', 6, 640),
        ], $u['amantto']);
        $inv->registrar(['tipo' => 'entrada_compra', 'fecha' => today()->subDays(47), 'proveedor_id' => $prov('Lubricantes y Filtros Express'), 'documento' => 'FAC-LF-0932'], [
            $linea('INS-ACE-46', 20, 520), $linea('INS-ACE-COMP', 10, 890), $linea('INS-GRASA', 70, 19.7),
            $linea('REP-FILT-AIRE', 3, 480), $linea('REP-FILT-ACE', 3, 365), $linea('REP-SEP-ACE', 1, 1850), $linea('REP-FILT-DSL', 4, 150),
        ], $u['amantto']);
        $inv->registrar(['tipo' => 'entrada_compra', 'fecha' => today()->subDays(45), 'proveedor_id' => $prov('Ferretería El Tornillo Industrial'), 'documento' => 'TICKET-77120'], [
            $linea('INS-WD40', 12, 48), $linea('INS-CEMENTO', 8, 95), $linea('INS-THINNER', 5, 85), $linea('INS-TRAPO', 40, 12),
            $linea('INS-ELEC-6013', 20, 22), $linea('INS-DISCO', 40, 14), $linea('INS-GUANTE', 6, 115), $linea('INS-LENTES', 24, 18),
            $linea('INS-TAPON', 400, 1.2), $linea('REP-SELLO-34', 2, 210), $linea('REP-CAP-ARR', 3, 145), $linea('REP-MANG-12', 20, 68),
        ], $u['gmantto']);
        $inv->registrar(['tipo' => 'entrada_compra', 'fecha' => today()->subDays(40), 'proveedor_id' => $prov('Taller Mecánico Precisión'), 'documento' => 'FAC-TMP-221'], [
            $linea('REP-CUCH-CT', 4, 350), $linea('REP-CUCH-MOL', 1, 2400), $linea('REP-BOQ-INY', 3, 480),
        ], $u['amantto']);

        // Ajustes
        $inv->registrar(['tipo' => 'ajuste_entrada', 'fecha' => today()->subDays(5),
            'notas' => 'Conteo físico: aparecieron 2 rodamientos en caja sin rotular'], [$linea('REP-ROD-6205', 2)], $u['gmantto']);
        $inv->registrar(['tipo' => 'ajuste_salida', 'fecha' => today(),
            'notas' => 'Discos de corte quebrados en la caja'], [$linea('INS-DISCO', 3)], $u['amantto']);
    }

    // ── Órdenes de trabajo en todos los estados ─────────────────────────
    private function ordenes(): void
    {
        $ots = app(OrdenTrabajoService::class);
        $inv = app(InventarioService::class);
        $u = $this->u;
        $m = fn (string $c) => $this->maq[$c]->id;

        // [máquina, título, tipo, especialidad, prioridad, responsable, inicio, vence, estado, progreso, nota, ayudantes]
        $abiertas = [
            ['EXT1', 'Cambio de resistencias zona 3 de extrusora', 'correctivo', 'Eléctrico', 'critica', 'kenneth', -3, -1, 'en_progreso', 60, 'Se cambiaron 2 de 3 resistencias; falta la del cabezal.', ['miguel']],
            ['EXT2', 'Vibración en caja reductora del halador', 'correctivo', 'Mecánico', 'alta', 'selvin', -6, 2, 'en_espera', 30, 'Esperando rodamiento 22212 E del proveedor.', []],
            ['MOL1', 'Afilado de cuchillas del molino', 'preventivo', 'Mecánico', 'media', 'canche', -1, 4, 'pendiente', 0, null, []],
            ['MEZ1', 'Fuga de aceite en sello de la mezcladora', 'correctivo', 'Hidráulico / Neumático', 'alta', 'djose', -9, -3, 'en_progreso', 45, 'Se desmontó la tapa; el sello está cristalizado.', []],
            ['TOR1', 'Limpieza de relleno de torre de enfriamiento', 'preventivo', 'Servicios generales', 'media', 'canche', 2, 12, 'pendiente', 0, null, []],
            ['PLE1', 'Prueba de arranque de planta eléctrica con carga', 'predictivo', 'Eléctrico', 'alta', 'miguel', -2, 1, 'en_progreso', 20, 'Se revisó nivel de combustible y batería.', ['kenneth']],
            ['MON1', 'Cambio de llantas delanteras del montacargas', 'correctivo', 'Mecánico', 'media', 'selvin', -15, -6, 'en_espera', 10, 'Cotización enviada a compras.', []],
            ['SEL1', 'Ajuste de mordazas de selladora', 'correctivo', 'Mecánico', 'baja', 'canche', 0, 9, 'pendiente', 0, null, []],
            ['INY3', 'Revisión de fugas en bloque hidráulico', 'predictivo', 'Hidráulico / Neumático', 'media', 'djose', -4, 5, 'en_progreso', 35, 'Se detectó goteo en conexión de manguera X7.', []],
            ['CT1', 'Cuchilla desgastada: cortes con rebaba', 'correctivo', 'Mecánico', 'alta', 'selvin', -1, 1, 'pendiente', 0, null, []],
            [null, 'Instalación de luminarias LED en bodega de PT', 'proyecto', 'Eléctrico', 'media', 'miguel', -20, 20, 'en_progreso', 55, 'Instaladas 14 de 24 luminarias.', ['kenneth']],
            [null, 'Señalización de rutas de evacuación', 'mejora', 'Servicios generales', 'baja', 'amantto', -5, 25, 'en_progreso', 25, 'Se compraron los rótulos fotoluminiscentes.', []],
            ['ELV1', 'Revisión de cables y frenos del elevador', 'preventivo', 'Mecánico', 'alta', 'selvin', -12, -2, 'pendiente', 0, null, []],
            ['COMPHZ', 'Temperatura alta en compresor 2', 'correctivo', 'Refrigeración', 'critica', 'djose', 0, 1, 'pendiente', 0, null, []],
        ];
        foreach ($abiertas as [$maq, $titulo, $tipo, $esp, $prio, $resp, $ini, $vence, $estado, $prog, $nota, $ayud]) {
            $ot = $ots->crear(['titulo' => $titulo, 'maquina_id' => $maq ? $m($maq) : null, 'tipo' => $tipo, 'especialidad_id' => $this->esp($esp),
                'prioridad' => $prio, 'responsable_id' => $u[$resp]->id, 'fecha_inicio' => today()->addDays($ini), 'fecha_vencimiento' => today()->addDays($vence),
                'ayudantes' => array_map(fn ($a) => $u[$a]->id, $ayud),
                'checklist' => [['texto' => 'Bloqueo y etiquetado (LOTO)', 'hecho' => $prog > 0], ['texto' => 'Ejecutar el trabajo', 'hecho' => false], ['texto' => 'Prueba de funcionamiento', 'hecho' => false]],
            ], $u['amantto']);
            if ($estado !== 'pendiente') {
                $ots->registrarSeguimiento($ot, ['estado' => $estado, 'progreso' => $prog, 'texto' => $nota,
                    'motivo_espera' => $estado === 'en_espera' ? $nota : null, 'horas' => round($prog / 20, 1)], $u[$resp]);
            }
        }

        // Completadas (llegan solas a la bitácora) con repuestos descontados de la bodega.
        $completadas = [
            ['COMPIR', 'Servicio de 4000 h a compresor de tornillo', 'preventivo', 'Mecánico', 'selvin', 44, 42, 5, 'Cambio de filtros de aire y aceite, separador y 5 gal de aceite sintético.', 'Unidad compresora',
                [['REP-FILT-AIRE', 1], ['REP-FILT-ACE', 1], ['REP-SEP-ACE', 1], ['INS-ACE-COMP', 5]], false],
            ['ENC1', 'Cambio de resistencias de encampanadora 1', 'correctivo', 'Eléctrico', 'kenneth', 37, 36, 3, 'Se reemplazaron 3 resistencias de 500W y una termocupla.', 'Calefacción',
                [['REP-RES-500', 3], ['REP-TERMO-K', 1]], true],
            ['BA1', 'Bomba de cisterna no arranca', 'correctivo', 'Eléctrico', 'miguel', 33, 32, 2, 'Capacitor de arranque dañado; se cambió y se probó presión.', 'Bomba centrífuga',
                [['REP-CAP-ARR', 1]], true],
            ['CA1', 'Sensor de posición de conectora falla intermitente', 'correctivo', 'Eléctrico', 'kenneth', 29, 27, 1.5, 'Se cambió sensor inductivo y fusible de control.', 'Sistema neumático',
                [['REP-SEN-IND', 1], ['REP-FUS-15', 1]], true],
            ['BTF', 'Rodamiento ruidoso en eje de bobinadora', 'correctivo', 'Mecánico', 'selvin', 24, 22, 4, 'Cambio de 2 rodamientos 6205 y ajuste de cadena.', 'Motorreductor',
                [['REP-ROD-6205', 2]], true],
            ['EXT1', 'Cambio de bandas del motor principal', 'preventivo', 'Mecánico', 'canche', 18, 17, 2, 'Se cambiaron las 3 bandas B-52 y se tensaron.', 'Motor principal',
                [['REP-BANDA-B52', 3]], false],
            ['PLE1', 'Cambio de filtros de combustible', 'preventivo', 'Mecánico', 'djose', 11, 10, 1, 'Filtros de combustible nuevos y purga del sistema.', 'Motor diésel',
                [['REP-FILT-DSL', 2]], false],
            ['CT1', 'Cambio de cuchilla de cortador', 'correctivo', 'Mecánico', 'canche', 7, 6, 1, 'Cuchilla nueva instalada y alineada.', 'Corte',
                [['REP-CUCH-CT', 1]], true],
            ['INY3', 'Cambio de manguera hidráulica X7', 'correctivo', 'Hidráulico / Neumático', 'djose', 4, 3, 2.5, 'Se armó manguera nueva de 1.5 m y se rellenó aceite ISO 46.', 'Bloque hidráulico',
                [['REP-MANG-12', 1.5], ['INS-ACE-46', 3]], true],
        ];
        foreach ($completadas as [$maq, $titulo, $tipo, $esp, $resp, $hace, $termino, $horas, $trabajo, $comp, $repuestos, $paro]) {
            $ot = $ots->crear(['titulo' => $titulo, 'maquina_id' => $m($maq), 'tipo' => $tipo, 'especialidad_id' => $this->esp($esp), 'prioridad' => $paro ? 'alta' : 'media',
                'responsable_id' => $u[$resp]->id, 'fecha_inicio' => today()->subDays($hace), 'fecha_vencimiento' => today()->subDays($termino + ($hace % 3 === 0 ? -1 : 1))], $u['amantto']);
            $ots->registrarSeguimiento($ot, ['estado' => 'en_progreso', 'progreso' => 50, 'texto' => 'Se inició el trabajo.', 'horas' => $horas / 2], $u[$resp]);
            $inv->registrar(['tipo' => 'consumo_mantenimiento', 'fecha' => today()->subDays($termino),
                'maquina_id' => $m($maq), 'orden_trabajo_id' => $ot->id, 'referencia' => $ot->folio],
                array_map(fn ($r) => ['producto_id' => $this->prod[$r[0]]->id, 'cantidad' => $r[1]], $repuestos), $u[$resp]);
            $ots->completar($ot, ['trabajo_realizado' => $trabajo, 'componente' => $comp, 'horas' => $horas / 2,
                'detuvo_maquina' => $paro, 'horas_paro' => $paro ? $horas + 1 : null], $u[$resp]);
            // Fechas reales del trabajo (el servicio usa "hoy").
            $ot->update(['completada_at' => today()->subDays($termino)->setTime(15, 30)]);
            $ot->bitacora()->update(['fecha' => today()->subDays($termino)]);
        }

        $cancelada = $ots->crear(['titulo' => 'Pintura de estructura de tolva', 'maquina_id' => $m('MEZ1'), 'tipo' => 'mejora', 'especialidad_id' => $this->esp('Servicios generales'),
            'prioridad' => 'baja', 'responsable_id' => $u['canche']->id, 'fecha_vencimiento' => today()->subDays(8)], $u['amantto']);
        $ots->cancelar($cancelada, 'Se hará en el paro de fin de año.', $u['gmantto']);

        // Falla reportada por producción, sin asignar.
        $ots->crear(['titulo' => 'Ruido extraño en inyectora 4 al cerrar molde', 'maquina_id' => $m('INY4'), 'tipo' => 'correctivo', 'prioridad' => 'alta',
            'descripcion' => 'Reportado por el operador del turno B.'], $u['supervisor']);
    }

    // ── Bitácora histórica (antes del sistema) ──────────────────────────
    private function bitacoraHistorica(): void
    {
        foreach ([
            ['EXT1', '2023-02-10', 'correctivo', 'Tornillo', 'Rectificado de tornillos y barril por desgaste', 'Taller Mecánico Precisión', 18500, 'Angel García'],
            ['EXT1', '2024-06-22', 'preventivo', 'Motor principal', 'Mantenimiento general de motor: rodamientos y limpieza', null, 2800, 'Angel García'],
            ['EXT2', '2024-01-15', 'mejora', 'Halador', 'Instalación de variador de velocidad en halador', null, 9600, 'Kenneth López'],
            ['MEZ1', '2023-09-05', 'correctivo', 'Transmisión', 'Cambio de rodamientos de eje principal', 'Rodamientos y Bandas de Guatemala', 5400, 'Selvin Ambrosio'],
            ['COMPIR', '2024-03-18', 'preventivo', 'Unidad compresora', 'Servicio de 4000 h con kit de filtros', 'Lubricantes y Filtros Express', 3900, 'Selvin Ambrosio'],
            ['COMPIR', '2024-11-02', 'preventivo', 'Unidad compresora', 'Servicio de 4000 h con kit de filtros', 'Lubricantes y Filtros Express', 4100, 'Selvin Ambrosio'],
            ['CH1', '2022-05-12', 'correctivo', 'Compresor', 'Falla de compresor de refrigeración: se recomienda dar de baja', 'Refrigeración Industrial Maya', 1200, 'Miguel Ramírez'],
            ['PLE1', '2024-08-30', 'predictivo', 'Motor diésel', 'Análisis de aceite y prueba de carga 80 %', null, 650, 'Miguel Ramírez'],
            ['MON1', '2024-04-10', 'correctivo', 'Hidráulico', 'Cambio de sellos de cilindro de elevación', 'Hydraserv', 3200, 'José Pérez'],
            ['ENC2', '2024-09-14', 'mejora', 'Moldes', 'Fabricación de molde de curvado 1/2"', null, 1800, 'Carlos Canché'],
            ['INY2', '2023-12-01', 'correctivo', 'Hidráulico', 'Cambio de bomba hidráulica por cavitación', 'Hydraserv', 14200, 'José Pérez'],
            ['BA2', '2024-02-20', 'correctivo', 'Bomba', 'Cambio de sello mecánico', null, 450, 'Miguel Ramírez'],
        ] as [$maq, $fecha, $tipo, $comp, $trabajo, $prov, $costo, $resp]) {
            Bitacora::create(['maquina_id' => $this->maq[$maq]->id, 'fecha' => $fecha, 'tipo' => $tipo, 'componente' => $comp,
                'trabajo_realizado' => $trabajo, 'proveedor_id' => $prov ? $this->prov[$prov]->id : null, 'costo' => $costo,
                'responsable_nombre' => $resp, 'horas' => round($costo / 1500 + 1, 1)]);
        }
    }

    // ── Planes preventivos ──────────────────────────────────────────────
    private function planes(): void
    {
        foreach ([
            ['EXT1', 'Revisión de resistencias y termocuplas de extrusora', 'meses', 1, 'Eléctrico', 'kenneth', 6, ['Medir amperaje por zona', 'Verificar lectura de termocuplas', 'Reapretar bornes']],
            ['EXT2', 'Lubricación de caja reductora', 'semanas', 2, 'Mecánico', 'selvin', 2, ['Revisar nivel de aceite', 'Engrasar rodamientos', 'Revisar temperatura']],
            ['MEZ1', 'Inspección de sellos y aspas de mezcladora', 'meses', 2, 'Mecánico', 'selvin', 18, ['Revisar desgaste de aspas', 'Revisar sello de eje', 'Limpiar ducto de venteo']],
            ['MOL1', 'Afilado de cuchillas de molino', 'semanas', 3, 'Mecánico', 'canche', 9, ['Desmontar cuchillas', 'Afilar y balancear', 'Ajustar holgura a 0.3 mm']],
            ['TOR1', 'Tratamiento químico del agua de la torre', 'dias', 15, 'Servicios generales', 'djose', 4, ['Medir pH y dureza', 'Dosificar biocida', 'Purga de fondo']],
            ['PLE1', 'Arranque semanal de planta eléctrica', 'semanas', 1, 'Eléctrico', 'miguel', 1, ['Revisar nivel de combustible', 'Arrancar 15 min', 'Revisar voltaje y frecuencia']],
            ['MON1', 'Servicio de 250 h del montacargas', 'meses', 3, 'Mecánico', 'selvin', 27, ['Cambio de aceite de motor', 'Revisar frenos', 'Revisar cadenas del mástil']],
            ['COMPHZ', 'Servicio de 4000 h a compresor 2', 'meses', 6, 'Mecánico', 'selvin', 40, ['Filtro de aire', 'Filtro de aceite', 'Separador', 'Aceite sintético']],
            ['MCA', 'Limpieza y calibración de sensores de conteo', 'meses', 1, 'Eléctrico', 'kenneth', 11, ['Limpiar lentes', 'Probar con 300 piezas', 'Ajustar sensibilidad']],
            ['SEL1', 'Cambio de teflón de mordazas', 'meses', 1, 'Mecánico', 'canche', 15, ['Cambiar teflón', 'Verificar temperatura de sellado']],
        ] as [$maq, $titulo, $unidad, $valor, $esp, $resp, $dias, $check]) {
            PlanMantenimiento::create(['maquina_id' => $this->maq[$maq]->id, 'titulo' => $titulo, 'checklist' => $check, 'tipo' => 'preventivo',
                'especialidad_id' => $this->esp($esp), 'responsable_id' => $this->u[$resp]->id, 'prioridad' => 'media',
                'frecuencia_valor' => $valor, 'frecuencia_unidad' => $unidad, 'proxima_fecha' => today()->addDays($dias),
                'dias_anticipacion' => $unidad === 'dias' ? 1 : 3, 'duracion_estimada' => 2]);
        }
        app(OrdenTrabajoService::class)->generarPreventivas(User::where('username', 'admin')->first());
    }

    // ── Herramientas ────────────────────────────────────────────────────
    private function herramientas(): void
    {
        foreach ([
            ['HER-009', 'Llave de impacto neumática 1/2"', 'Neumática', 'Ingersoll Rand', 'djose', 1850],
            ['HER-010', 'Cámara termográfica', 'Medición', 'FLIR', 'kenneth', 9800],
            ['HER-011', 'Megóhmetro 1000V', 'Medición', 'Fluke', 'miguel', 7200],
            ['HER-012', 'Extractor de rodamientos 3 garras', 'Manual', 'Truper', 'selvin', 420],
            ['HER-013', 'Nivel láser de líneas', 'Medición', 'Bosch', null, 1650],
            ['HER-014', 'Pulidora 7"', 'Eléctrica', 'Makita', 'canche', 1350],
            ['HER-015', 'Juego de llaves Allen métricas', 'Manual', 'Stanley', null, 180],
            ['HER-016', 'Tacómetro digital láser', 'Medición', 'Extech', null, 890],
            ['HER-017', 'Pistola de calor', 'Eléctrica', 'DeWalt', 'kenneth', 720],
            ['HER-018', 'Gato hidráulico de botella 20 t', 'Hidráulica', 'Truper', null, 560],
        ] as [$cod, $nom, $cat, $marca, $resp, $costo]) {
            $h = Herramienta::create(['codigo' => $cod, 'nombre' => $nom, 'categoria' => $cat, 'marca' => $marca, 'costo' => $costo,
                'fecha_compra' => today()->subMonths(crc32($cod) % 30 + 2), 'estado' => $resp ? 'asignada' : 'disponible',
                'asignada_a' => $resp ? $this->u[$resp]->id : null]);
            if ($resp) {
                $h->asignaciones()->create(['user_id' => $this->u[$resp]->id, 'entregado_por' => $this->u['amantto']->id,
                    'entregado_at' => now()->subDays(crc32($cod) % 60 + 3), 'estado_entrega' => 'bueno']);
            }
        }
        // Una con historial completo y otra en reparación.
        $h = Herramienta::create(['codigo' => 'HER-019', 'nombre' => 'Rotomartillo SDS plus', 'categoria' => 'Eléctrica', 'marca' => 'Bosch', 'costo' => 2100, 'estado' => 'en_reparacion']);
        $h->asignaciones()->create(['user_id' => $this->u['selvin']->id, 'entregado_por' => $this->u['amantto']->id, 'entregado_at' => now()->subDays(40),
            'estado_entrega' => 'bueno', 'recibido_por' => $this->u['amantto']->id, 'devuelto_at' => now()->subDays(6), 'estado_devolucion' => 'danado', 'notas' => 'Carbones gastados']);
    }
}
