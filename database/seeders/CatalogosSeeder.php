<?php

namespace Database\Seeders;

use App\Models\Area;
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

        foreach (['Repuestos eléctricos', 'Repuestos mecánicos', 'Repuestos hidráulicos', 'Repuestos neumáticos',
            'Lubricantes', 'Consumibles'] as $c) {
            CategoriaProducto::firstOrCreate(['nombre' => $c]);
        }
    }
}
