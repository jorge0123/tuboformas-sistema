<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Bodega;
use App\Models\CategoriaProducto;
use App\Models\Especialidad;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Catálogos simples editables por el administrador. */
class CatalogoController extends Controller
{
    private const CATALOGOS = [
        'areas' => ['Áreas de planta', Area::class, 'Dónde está cada máquina.'],
        'especialidades' => ['Especialidades técnicas', Especialidad::class, 'Mecánico, eléctrico… para asignar y filtrar órdenes.'],
        'bodegas' => ['Bodegas', Bodega::class, 'Lugares donde se guarda inventario.'],
        'categorias' => ['Categorías de producto', CategoriaProducto::class, 'Para agrupar el inventario.'],
        'unidades' => ['Unidades de medida', Unidad::class, 'Unidad base de cada producto.'],
    ];

    public function index(?string $catalogo = 'areas')
    {
        $this->authorize('catalogos.gestionar');
        abort_unless(isset(self::CATALOGOS[$catalogo]), 404);
        [$titulo, $modelo, $ayuda] = self::CATALOGOS[$catalogo];

        return view('catalogos.index', [
            'catalogos' => self::CATALOGOS, 'actual' => $catalogo, 'titulo' => $titulo, 'ayuda' => $ayuda,
            'items' => $modelo::orderByDesc('activo')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, string $catalogo)
    {
        $this->authorize('catalogos.gestionar');
        abort_unless(isset(self::CATALOGOS[$catalogo]), 404);
        $modelo = self::CATALOGOS[$catalogo][1];
        $item = $modelo::create($this->validar($request, $catalogo) + ['activo' => true]);
        Auditoria::registrar('crear', $item, $item->nombre);

        return back()->with('ok', 'Agregado.');
    }

    public function update(Request $request, string $catalogo, int $id)
    {
        $this->authorize('catalogos.gestionar');
        abort_unless(isset(self::CATALOGOS[$catalogo]), 404);
        $item = self::CATALOGOS[$catalogo][1]::findOrFail($id);
        $item->update($this->validar($request, $catalogo, $id) + ['activo' => $request->boolean('activo')]);
        Auditoria::registrar('editar', $item, $item->nombre);

        return back()->with('ok', 'Guardado.');
    }

    private function validar(Request $request, string $catalogo, ?int $id = null): array
    {
        $tabla = (new (self::CATALOGOS[$catalogo][1]))->getTable();
        $reglas = ['nombre' => ['required', 'string', 'max:100', Rule::unique($tabla, 'nombre')->ignore($id)]];
        if ($catalogo === 'unidades') {
            $reglas['abreviatura'] = ['required', 'string', 'max:20'];
        }
        if ($catalogo === 'bodegas') {
            $reglas['codigo'] = ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('bodegas', 'codigo')->ignore($id)];
            $reglas['descripcion'] = ['nullable', 'string', 'max:255'];
            unset($reglas['nombre'][3]);
        }

        return $request->validate($reglas);
    }
}
