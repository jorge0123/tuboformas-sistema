<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('proveedores.ver');
        $proveedores = Proveedor::withCount(['productos', 'bitacoras'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', '%'.$request->q.'%')
                ->orWhere('nit', 'like', '%'.$request->q.'%')->orWhere('contacto', 'like', '%'.$request->q.'%')))
            ->when($request->filled('tipo'), fn ($q) => $q->whereJsonContains('tipos', $request->tipo))
            ->when($request->input('estado', 'activos') === 'activos', fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')->paginate(30)->withQueryString();

        return view('proveedores.index', compact('proveedores'));
    }

    public function show(Proveedor $proveedor)
    {
        $this->authorize('proveedores.ver');
        $proveedor->load(['productos.unidad']);
        $servicios = $proveedor->bitacoras()->with('maquina')->latest('fecha')->limit(20)->get();
        $disponibles = auth()->user()->can('proveedores.gestionar')
            ? Producto::where('activo', true)->whereNotIn('id', $proveedor->productos->pluck('id'))->orderBy('nombre')->get(['id', 'codigo', 'nombre'])
            : collect();

        return view('proveedores.show', compact('proveedor', 'servicios', 'disponibles'));
    }

    public function create()
    {
        $this->authorize('proveedores.gestionar');

        return view('proveedores.form', ['p' => new Proveedor(['activo' => true, 'tipos' => []])]);
    }

    public function store(Request $request)
    {
        $this->authorize('proveedores.gestionar');
        $p = Proveedor::create($this->validar($request));
        Auditoria::registrar('crear', $p, $p->nombre);

        return redirect()->route('proveedores.show', $p)->with('ok', 'Proveedor registrado.');
    }

    public function edit(Proveedor $proveedor)
    {
        $this->authorize('proveedores.gestionar');

        return view('proveedores.form', ['p' => $proveedor]);
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $this->authorize('proveedores.gestionar');
        $proveedor->update($this->validar($request));
        Auditoria::registrar('editar', $proveedor, $proveedor->nombre);

        return redirect()->route('proveedores.show', $proveedor)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Proveedor $proveedor)
    {
        $this->authorize('proveedores.gestionar');
        if ($proveedor->bitacoras()->exists() || \App\Models\Movimiento::where('proveedor_id', $proveedor->id)->exists()) {
            $proveedor->update(['activo' => false]);

            return redirect()->route('proveedores.index')->with('ok', 'El proveedor tiene historial: se desactivó en lugar de eliminarse.');
        }
        Auditoria::registrar('eliminar', $proveedor, $proveedor->nombre);
        $proveedor->delete();

        return redirect()->route('proveedores.index')->with('ok', 'Proveedor eliminado.');
    }

    public function agregarProducto(Request $request, Proveedor $proveedor)
    {
        $this->authorize('proveedores.gestionar');
        $d = $request->validate([
            'producto_id' => ['required', 'exists:productos,id'],
            'codigo_proveedor' => ['nullable', 'string', 'max:60'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'dias_entrega' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);
        $proveedor->productos()->syncWithoutDetaching([$d['producto_id'] => collect($d)->except('producto_id')->all()]);

        return back()->with('ok', 'Producto agregado al catálogo del proveedor.');
    }

    public function quitarProducto(Proveedor $proveedor, Producto $producto)
    {
        $this->authorize('proveedores.gestionar');
        $proveedor->productos()->detach($producto->id);

        return back()->with('ok', 'Producto quitado del catálogo.');
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:30'],
            'contacto' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'tipos' => ['nullable', 'array'],
            'tipos.*' => [Rule::in(array_keys(Proveedor::TIPOS))],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
        $d['tipos'] = $d['tipos'] ?? [];
        $d['activo'] = $request->boolean('activo');

        return $d;
    }
}
