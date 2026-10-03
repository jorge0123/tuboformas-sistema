<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\MaquinaParte;
use App\Models\MovimientoLinea;
use App\Models\Producto;
use App\Models\Unidad;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('inventario.ver');
        $productos = $this->filtrar($request)->with(['unidad', 'categoria'])->orderBy('nombre')->paginate(30)->withQueryString();
        $activos = Producto::where('activo', true);
        $valor = $request->user()->can('inventario.ver_costos')
            ? (float) (clone $activos)->sum(DB::raw('existencia * costo_promedio')) : null;
        $categorias = CategoriaProducto::where('activo', true)->orderBy('nombre')->get();

        return view('productos.index', ['productos' => $productos, 'total' => (clone $activos)->count(),
            'bajos' => (clone $activos)->bajoMinimo()->count(), 'valor' => $valor, 'categorias' => $categorias]);
    }

    public function exportar(Request $request)
    {
        $this->authorize('inventario.ver');
        $costos = $request->user()->can('inventario.ver_costos');
        $filas = $this->filtrar($request)->with(['unidad', 'categoria'])->orderBy('nombre')->get()->map(function ($p) use ($costos) {
            $fila = [$p->codigo, $p->nombre, Producto::TIPOS[$p->tipo], $p->categoria?->nombre, $p->medida, $p->ubicacion,
                $p->unidad->abreviatura, (float) $p->existencia, (float) $p->stock_minimo];
            if ($costos) {
                array_push($fila, (float) $p->costo_promedio, round((float) $p->existencia * (float) $p->costo_promedio, 2));
            }

            return $fila;
        });
        $enc = ['Código', 'Repuesto', 'Tipo', 'Categoría', 'Medida', 'Ubicación', 'Unidad', 'Existencia', 'Mínimo'];
        if ($costos) {
            array_push($enc, 'Costo promedio', 'Valor');
        }

        return Csv::descargar('repuestos', $enc, $filas);
    }

    /** JSON para buscadores. */
    public function buscar(Request $request)
    {
        $this->authorize('inventario.ver');

        return Producto::where('activo', true)->buscar($request->input('q'))->with('unidad')
            ->limit(20)->get()->map(fn ($p) => [
                'id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'unidad' => $p->unidad->abreviatura,
                'existencia' => (float) $p->existencia,
            ]);
    }

    public function show(Request $request, Producto $producto)
    {
        $this->authorize('inventario.ver');
        $producto->load(['unidad', 'categoria', 'proveedores', 'archivos.user']);
        $kardex = $request->user()->can('movimientos.ver')
            ? MovimientoLinea::with(['movimiento.user', 'movimiento.orden', 'movimiento.maquina'])
                ->where('producto_id', $producto->id)
                ->whereHas('movimiento', fn ($q) => $q->whereIn('estado', ['confirmado', 'anulado']))
                ->orderByDesc('id')->paginate(25)->withQueryString()
            : null;
        $maquinas = MaquinaParte::with('maquina')->where('producto_id', $producto->id)->get();

        return view('productos.show', compact('producto', 'kardex', 'maquinas'));
    }

    public function create()
    {
        $this->authorize('inventario.gestionar');

        return view('productos.form', $this->datos(new Producto(['tipo' => 'repuesto', 'activo' => true, 'stock_minimo' => 0])));
    }

    public function store(Request $request)
    {
        $this->authorize('inventario.gestionar');
        $p = Producto::create($this->validar($request));
        Auditoria::registrar('crear', $p, $p->etiqueta());

        return redirect()->route('productos.show', $p)->with('ok', 'Repuesto creado.');
    }

    public function edit(Producto $producto)
    {
        $this->authorize('inventario.gestionar');

        return view('productos.form', $this->datos($producto));
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorize('inventario.gestionar');
        $producto->update($this->validar($request, $producto));
        Auditoria::registrar('editar', $producto, $producto->etiqueta());

        return redirect()->route('productos.show', $producto)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Producto $producto)
    {
        $this->authorize('inventario.gestionar');
        if ($producto->lineas()->exists()) {
            $producto->update(['activo' => false]);

            return redirect()->route('productos.index')->with('ok', 'El repuesto tiene movimientos: se desactivó en lugar de eliminarse.');
        }
        Auditoria::registrar('eliminar', $producto, $producto->etiqueta());
        $producto->delete();

        return redirect()->route('productos.index')->with('ok', 'Repuesto eliminado.');
    }

    private function filtrar(Request $request)
    {
        return Producto::query()->buscar($request->input('q'))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->categoria))
            ->when($request->boolean('bajo_minimo'), fn ($q) => $q->bajoMinimo())
            ->when(! $request->boolean('inactivos'), fn ($q) => $q->where('activo', true));
    }

    private function datos(Producto $p): array
    {
        return [
            'p' => $p,
            'categorias' => CategoriaProducto::where('activo', true)->orderBy('nombre')->get(),
            'unidades' => Unidad::where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    private function validar(Request $request, ?Producto $p = null): array
    {
        $d = $request->validate([
            'codigo' => ['required', 'string', 'max:40', Rule::unique('productos')->ignore($p)],
            'nombre' => ['required', 'string', 'max:200'],
            'tipo' => ['required', Rule::in(array_keys(Producto::TIPOS))],
            'categoria_id' => ['nullable', 'exists:categorias_producto,id'],
            'medida' => ['nullable', 'string', 'max:50'],
            'unidad_id' => ['required', 'exists:unidades,id'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'costo_promedio' => ['nullable', 'numeric', 'min:0'],
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'max:8192'],
        ], [], ['codigo' => 'código', 'unidad_id' => 'unidad']);
        $d['stock_minimo'] = $d['stock_minimo'] ?? 0;
        $d['activo'] = $request->boolean('activo');
        // El costo promedio lo lleva el sistema; solo se captura a mano al crear (inventario inicial).
        if ($p || ! $request->user()->can('inventario.ver_costos')) {
            unset($d['costo_promedio']);
        }
        if ($request->hasFile('foto')) {
            if ($p?->foto) {
                Storage::disk('public')->delete($p->foto);
            }
            $d['foto'] = $request->file('foto')->store('productos', 'public');
        } else {
            unset($d['foto']);
        }

        return $d;
    }
}
