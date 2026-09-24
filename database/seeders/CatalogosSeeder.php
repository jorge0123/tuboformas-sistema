<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Bodega;
use App\Models\CategoriaProducto;
use App\Models\Especialidad;
use App\Models\Unidad;
use Illuminate\Database\Seeder;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        // Áreas tal como aparecen en el "Listado de Maquinas Tuboformas".
        foreach (['Taller de Maquinado', 'Taller Mecánico', 'Empaque', 'Extrusión', 'Inyección',
            'Termoformado', 'Servicios', 'Vehículos', 'Bodega', 'Planta general'] as $a) {
            Area::firstOrCreate(['nombre' => $a]);
        }

        foreach (['Mecánico', 'Eléctrico', 'Electromecánico', 'Hidráulico / Neumático',
            'Refrigeración', 'Soldadura', 'Servicios generales'] as $e) {
            Especialidad::firstOrCreate(['nombre' => $e]);
        }

        foreach ([
            ['Unidad', 'u'], ['Pieza', 'pza'], ['Tubo', 'tubo'], ['Manojo', 'manojo'], ['Kilogramo', 'kg'],
            ['Libra', 'lb'], ['Metro', 'm'], ['Litro', 'L'], ['Galón', 'gal'], ['Caja', 'caja'], ['Bolsa', 'bolsa'],
        ] as [$n, $abr]) {
            Unidad::firstOrCreate(['nombre' => $n], ['abreviatura' => $abr]);
        }

        foreach (['Tubería PVC', 'Coplas', 'Codos / vueltas', 'Conectores', 'Cajas', 'Resina y aditivos',
            'Empaque', 'Repuestos eléctricos', 'Repuestos mecánicos', 'Repuestos hidráulicos',
            'Lubricantes', 'Consumibles'] as $c) {
            CategoriaProducto::firstOrCreate(['nombre' => $c]);
        }

        foreach ([
            ['MP', 'Bodega de materia prima', 'Tubería, resina y material que entra a producción'],
            ['PT', 'Bodega de producto terminado', 'Producto contado y empacado'],
            ['REP', 'Bodega de repuestos', 'Repuestos, lubricantes y consumibles de mantenimiento'],
        ] as [$cod, $nom, $desc]) {
            Bodega::firstOrCreate(['codigo' => $cod], ['nombre' => $nom, 'descripcion' => $desc]);
        }
    }
}
