<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Bodega;
use App\Models\CategoriaProducto;
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
        $bodegas = Bodega::where('activo', true)->orderBy('id')->get();
        $productos = $this->filtrar($request)->with(['unidad', 'categoria', 'existencias'])
            ->withSum('existencias', 'cantidad')->orderBy('nombre')->get();
        if ($request->boolean('bajo_minimo')) {
            $productos = $productos->filter->bajoMinimo();
        }
        $pagina = new \Illuminate\Pagination\LengthAwarePaginator(
            $productos->forPage($request->integer('page', 1), 30)->values(), $productos->count(), 30,
            $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->query()]
        );
        $valor = $request->user()->can('inventario.ver_costos')
            ? $productos->sum(fn ($p) => $p->stockTotal() * (float) $p->costo_promedio) : null;
        $categorias = CategoriaProducto::where('activo', true)->orderBy('nombre')->get();

        return view('productos.index', ['productos' => $pagina, 'total' => $productos->count(),
            'bajos' => $productos->filter->bajoMinimo()->count(), 'valor' => $valor, 'bodegas' => $bodegas, 'categorias' => $categorias]);
    }

    public function exportar(Request $request)
    {
        $this->authorize('inventario.ver');
        $costos = $request->user()->can('inventario.ver_costos');
        $bodegas = Bodega::where('activo', true)->orderBy('id')->get();
        $filas = $this->filtrar($request)->with(['unidad', 'categoria', 'existencias'])->orderBy('nombre')->get()->map(function ($p) use ($bodegas, $costos) {
            $fila = [$p->codigo, $p->nombre, Producto::TIPOS[$p->tipo], $p->categoria?->nombre, $p->medida, $p->unidad->abreviatura];
            foreach ($bodegas as $b) {
                $fila[] = (float) ($p->existencias->firstWhere('bodega_id', $b->id)?->cantidad ?? 0);
            }
            $total = (float) $p->existencias->sum('cantidad');
            array_push($fila, $total, (float) $p->stock_minimo);
            if ($costos) {
                array_push($fila, (float) $p->costo_promedio, round($total * (float) $p->costo_promedio, 2));
            }

            return $fila;
        });
        $enc = array_merge(['Código', 'Producto', 'Tipo', 'Categoría', 'Medida', 'Unidad'], $bodegas->pluck('nombre')->all(), ['Total', 'Mínimo']);
        if ($costos) {
            array_push($enc, 'Costo promedio', 'Valor');
        }

        return Csv::descargar('inventario', $enc, $filas);
    }

    /** JSON para buscadores (formulario de movimientos, conteos). */
    public function buscar(Request $request)
    {
        $this->authorize('inventario.ver');

        return Producto::where('activo', true)->buscar($request->input('q'))->with(['unidad', 'presentaciones'])
            ->limit(20)->get()->map(fn ($p) => [
                'id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'unidad' => $p->unidad->abreviatura,
                'presentaciones' => $p->presentaciones->map->only(['id', 'nombre', 'factor']),
            ]);
    }

    public function show(Request $request, Producto $producto)
    {
        $this->authorize('inventario.ver');
        $producto->load(['unidad', 'categoria', 'presentaciones', 'existencias.bodega', 'proveedores', 'archivos.user']);
        $kardex = $request->user()->can('movimientos.ver')
            ? MovimientoLinea::with(['movimiento.bodegaOrigen', 'movimiento.bodegaDestino', 'movimiento.user', 'presentacion'])
                ->where('producto_id', $producto->id)
                ->whereHas('movimiento', fn ($q) => $q->whereIn('estado', ['confirmado', 'anulado']))
                ->when($request->filled('bodega'), fn ($q) => $q->whereHas('movimiento', fn ($m) => $m->where('bodega_origen_id', $request->bodega)->orWhere('bodega_destino_id', $request->bodega)))
                ->orderByDesc('id')->paginate(25)->withQueryString()
            : null;
        $bodegas = Bodega::where('activo', true)->get();

        return view('productos.show', compact('producto', 'kardex', 'bodegas'));
    }

    public function create()
    {
        $this->authorize('inventario.gestionar');

        return view('productos.form', $this->datos(new Producto(['tipo' => 'producto_terminado', 'activo' => true, 'stock_minimo' => 0])));
    }

    public function store(Request $request)
    {
        $this->authorize('inventario.gestionar');
        $p = DB::transaction(function () use ($request) {
            $p = Producto::create($this->validar($request));
            $this->guardarPresentaciones($p, $request);

            return $p;
        });
        Auditoria::registrar('crear', $p, $p->etiqueta());

        return redirect()->route('productos.show', $p)->with('ok', 'Producto creado.');
    }

    public function edit(Producto $producto)
    {
        $this->authorize('inventario.gestionar');
        $producto->load('presentaciones');

        return view('productos.form', $this->datos($producto));
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorize('inventario.gestionar');
        DB::transaction(function () use ($request, $producto) {
            $producto->update($this->validar($request, $producto));
            $this->guardarPresentaciones($producto, $request);
        });
        Auditoria::registrar('editar', $producto, $producto->etiqueta());

        return redirect()->route('productos.show', $producto)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Producto $producto)
    {
        $this->authorize('inventario.gestionar');
        if ($producto->lineas()->exists()) {
            $producto->update(['activo' => false]);

            return redirect()->route('productos.index')->with('ok', 'El producto tiene movimientos: se desactivó en lugar de eliminarse.');
        }
        Auditoria::registrar('eliminar', $producto, $producto->etiqueta());
        $producto->delete();

        return redirect()->route('productos.index')->with('ok', 'Producto eliminado.');
    }

    private function filtrar(Request $request)
    {
        return Producto::query()->buscar($request->input('q'))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->categoria))
            ->when($request->filled('bodega'), fn ($q) => $q->whereHas('existencias', fn ($e) => $e->where('bodega_id', $request->bodega)->where('cantidad', '>', 0)))
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
            'color' => ['nullable', 'string', 'max:40'],
            'unidad_id' => ['required', 'exists:unidades,id'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'costo_promedio' => ['nullable', 'numeric', 'min:0'],
            'ubicacion' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'max:8192'],
            'presentaciones' => ['nullable', 'array'],
            'presentaciones.*.nombre' => ['nullable', 'string', 'max:60'],
            'presentaciones.*.factor' => ['nullable', 'numeric', 'gt:0'],
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
        unset($d['presentaciones']);

        return $d;
    }

    private function guardarPresentaciones(Producto $p, Request $request): void
    {
        $vienen = collect($request->input('presentaciones', []))->filter(fn ($x) => filled($x['nombre'] ?? null) && ($x['factor'] ?? 0) > 0);
        $ids = [];
        foreach ($vienen as $x) {
            $pres = ! empty($x['id']) ? $p->presentaciones()->find($x['id']) : null;
            if ($pres) {
                // Una presentación usada en movimientos no cambia de factor (el kárdex quedaría mal).
                $usada = MovimientoLinea::where('presentacion_id', $pres->id)->exists();
                $pres->update(['nombre' => $x['nombre']] + ($usada ? [] : ['factor' => $x['factor']]));
            } else {
                $pres = $p->presentaciones()->create(['nombre' => $x['nombre'], 'factor' => $x['factor']]);
            }
            $ids[] = $pres->id;
        }
        // Las que se quitaron del formulario se borran, salvo que ya estén en algún movimiento.
        $usadas = MovimientoLinea::whereNotNull('presentacion_id')->where('producto_id', $p->id)->distinct()->pluck('presentacion_id')->all();
        $p->presentaciones()->whereNotIn('id', array_merge($ids, $usadas))->delete();
    }
}
