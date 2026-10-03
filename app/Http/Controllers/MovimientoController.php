<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\InventarioService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MovimientoController extends Controller
{
    public function __construct(private InventarioService $inv) {}

    public function index(Request $request)
    {
        $this->authorize('movimientos.ver');
        $movimientos = $this->filtrar($request)->with(['user', 'proveedor', 'orden'])->withCount('lineas')
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(30)->withQueryString();
        $pendientes = Movimiento::where('estado', 'pendiente')->count();

        return view('movimientos.index', compact('movimientos', 'pendientes'));
    }

    public function exportar(Request $request)
    {
        $this->authorize('movimientos.ver');
        $costos = $request->user()->can('inventario.ver_costos');
        $filas = $this->filtrar($request)->with(['lineas.producto.unidad', 'proveedor', 'orden', 'user'])
            ->orderBy('fecha')->orderBy('id')->get()
            ->flatMap(fn ($m) => $m->lineas->map(fn ($l) => [
                $m->folio, $m->fecha->format('d/m/Y'), $m->nombreTipo(), Movimiento::ESTADOS[$m->estado],
                $l->producto->codigo, $l->producto->nombre, $m->efecto === 'salida' ? -(float) $l->cantidad : (float) $l->cantidad,
                $l->producto->unidad->abreviatura, $costos ? (float) $l->costo_unitario : '', $m->proveedor?->nombre,
                $m->orden?->folio, $m->documento, $m->referencia, $m->user->name,
            ]));

        return Csv::descargar('movimientos-repuestos', ['Folio', 'Fecha', 'Tipo', 'Estado', 'Código', 'Repuesto', 'Cantidad',
            'Unidad', 'Costo unitario', 'Proveedor', 'OT', 'Documento', 'Referencia', 'Registró'], $filas);
    }

    public function create(Request $request)
    {
        $this->authorize('movimientos.crear');
        $tipo = $request->input('tipo');
        if (! $tipo || ! isset(Movimiento::tiposCapturables()[$tipo]) || $tipo === 'consumo_mantenimiento') {
            return view('movimientos.tipos');
        }

        $productos = Producto::where('activo', true)->with('unidad')->orderBy('nombre')->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'unidad' => $p->unidad->abreviatura,
                'stock' => (float) $p->existencia,
            ]);

        return view('movimientos.form', [
            'tipo' => $tipo,
            'config' => Movimiento::TIPOS[$tipo],
            'productos' => $productos,
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productoInicial' => $request->integer('producto') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('movimientos.crear');
        $d = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Movimiento::tiposCapturables()))],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'documento' => ['nullable', 'string', 'max:60'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'exists:productos,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'fotos' => ['nullable', 'array', 'max:10'],
            'fotos.*' => ['image', 'max:10240'],
        ], [], ['lineas' => 'repuestos', 'lineas.*.cantidad' => 'cantidad', 'lineas.*.producto_id' => 'repuesto']);
        if (! $request->user()->can('inventario.ver_costos')) {
            foreach ($d['lineas'] as &$l) {
                unset($l['costo_unitario']);
            }
            unset($l);
        }

        $mov = $this->inv->registrar(collect($d)->except(['lineas', 'fotos'])->all(), $d['lineas'], $request->user());

        foreach ($request->file('fotos', []) as $foto) {
            $ruta = $foto->storeAs('movimiento/'.now()->format('Y/m'), Str::uuid().'.'.($foto->extension() ?: 'jpg'), 'public');
            $mov->archivos()->create(['categoria' => 'evidencia', 'nombre' => $foto->getClientOriginalName(), 'ruta' => $ruta,
                'mime' => $foto->getMimeType(), 'tamano' => $foto->getSize(), 'user_id' => $request->user()->id]);
        }

        return redirect()->route('movimientos.show', $mov)->with('ok', $mov->estado === 'pendiente'
            ? "{$mov->folio} registrado. Queda pendiente de aprobación."
            : "{$mov->folio} registrado. Las existencias ya se actualizaron.");
    }

    public function show(Movimiento $movimiento)
    {
        $this->authorize('movimientos.ver');
        $movimiento->load(['lineas.producto.unidad', 'proveedor',
            'maquina', 'orden', 'user', 'aprobador', 'origen', 'reverso', 'archivos.user']);

        return view('movimientos.show', ['m' => $movimiento]);
    }

    public function aprobar(Request $request, Movimiento $movimiento)
    {
        $this->authorize('movimientos.aprobar');
        $this->inv->aprobar($movimiento, $request->user());

        return back()->with('ok', 'Ajuste aprobado y aplicado a las existencias.');
    }

    public function rechazar(Request $request, Movimiento $movimiento)
    {
        $this->authorize('movimientos.aprobar');
        $d = $request->validate(['motivo' => ['required', 'string', 'max:255']]);
        $this->inv->rechazar($movimiento, $request->user(), $d['motivo']);

        return back()->with('ok', 'Ajuste rechazado. Se avisó a quien lo registró.');
    }

    public function anular(Request $request, Movimiento $movimiento)
    {
        $this->authorize('movimientos.anular');
        $d = $request->validate(['motivo' => ['required', 'string', 'max:255']]);
        $reverso = $this->inv->anular($movimiento, $request->user(), $d['motivo']);

        return back()->with('ok', "Movimiento anulado con el reverso {$reverso->folio}.");
    }

    private function filtrar(Request $request)
    {
        return Movimiento::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('folio', 'like', '%'.$request->q.'%')
                ->orWhere('documento', 'like', '%'.$request->q.'%')->orWhere('referencia', 'like', '%'.$request->q.'%')
                ->orWhereHas('lineas.producto', fn ($p) => $p->buscar($request->q))))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta));
    }
}
