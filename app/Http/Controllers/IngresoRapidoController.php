<?php

namespace App\Http\Controllers;

use App\Models\Bodega;
use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Ingreso de producto terminado desde el celular: se escanea (o se fotografía) el QR de la
 * bolsa/caja, el sistema propone producto y cantidad, el auxiliar confirma o corrige y al
 * final registra un solo movimiento "ingreso_produccion" con todas las líneas.
 */
class IngresoRapidoController extends Controller
{
    public function __construct(private InventarioService $inv) {}

    public function index()
    {
        $this->authorize('movimientos.crear');

        return view('bodega.ingreso-rapido', [
            'productos' => $this->catalogo(),
            'bodegas' => Bodega::where('activo', true)->orderBy('id')->get(['id', 'codigo', 'nombre']),
            'bodegaInicial' => Bodega::where('codigo', 'PT')->value('id'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('movimientos.crear');
        $d = $request->validate([
            'bodega_destino_id' => ['required', 'exists:bodegas,id'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'exists:productos,id'],
            'lineas.*.presentacion_id' => ['nullable', 'exists:producto_presentaciones,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'fotos' => ['nullable', 'array', 'max:10'],
            'fotos.*' => ['image', 'max:10240'],
        ], [], ['lineas' => 'productos', 'lineas.*.cantidad' => 'cantidad']);

        $mov = $this->inv->registrar([
            'tipo' => 'ingreso_produccion',
            'fecha' => today(),
            'bodega_destino_id' => $d['bodega_destino_id'],
            'referencia' => $d['referencia'] ?? null,
            'notas' => 'Registrado con ingreso rápido (QR).',
        ], $d['lineas'], $request->user());

        foreach ($request->file('fotos', []) as $foto) {
            $ruta = $foto->storeAs('movimiento/'.now()->format('Y/m'), Str::uuid().'.'.($foto->extension() ?: 'jpg'), 'public');
            $mov->archivos()->create(['categoria' => 'evidencia', 'nombre' => $foto->getClientOriginalName(), 'ruta' => $ruta,
                'mime' => $foto->getMimeType(), 'tamano' => $foto->getSize(), 'user_id' => $request->user()->id]);
        }

        return redirect()->route('bodega.ingreso-rapido')
            ->with('ok', "{$mov->folio} registrado: ".count($d['lineas']).' '.(count($d['lineas']) === 1 ? 'producto' : 'productos').' ingresaron a bodega.')
            ->with('ultimo', $mov->id);
    }

    /**
     * Arma una hoja de etiquetas QR para pegar en bolsas o cajas: se elige producto, presentación
     * y cuántas copias (ej. 20 × Copla 3/4" gris, bolsa x 300) y se imprime.
     */
    public function etiquetas(Request $request)
    {
        abort_unless($request->user()->canAny(['inventario.gestionar', 'movimientos.crear']), 403);
        $catalogo = Producto::where('activo', true)->with(['unidad', 'presentaciones'])->orderBy('nombre')->orderBy('color')->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'color' => $p->color, 'medida' => $p->medida,
                'tipo' => $p->tipo, 'unidad' => $p->unidad->abreviatura,
                'presentaciones' => $p->presentaciones->map(fn ($x) => ['id' => $x->id, 'nombre' => $x->nombre, 'factor' => (float) $x->factor])->values(),
            ])->values();

        // Desde la ficha del producto: ?producto=6 (&presentacion=7&copias=20)
        $inicial = null;
        if ($p = $catalogo->firstWhere('id', $request->integer('producto'))) {
            $pres = collect($p['presentaciones'])->firstWhere('id', $request->integer('presentacion')) ?? ($p['presentaciones'][0] ?? null);
            $inicial = ['producto_id' => $p['id'], 'presentacion_id' => $pres['id'] ?? null, 'copias' => max(1, min(500, $request->integer('copias', 1)))];
        }

        return view('bodega.etiquetas', compact('catalogo', 'inicial'));
    }

    private function catalogo()
    {
        return Producto::where('activo', true)->where('tipo', 'producto_terminado')
            ->with(['unidad', 'presentaciones'])->orderBy('nombre')->orderBy('color')->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'color' => $p->color,
                'medida' => $p->medida, 'unidad' => $p->unidad->abreviatura,
                // Variantes = mismo nombre, distinto color.
                'familia' => Str::lower(trim($p->nombre)),
                'presentaciones' => $p->presentaciones->map(fn ($x) => ['id' => $x->id, 'nombre' => $x->nombre, 'factor' => (float) $x->factor])->values(),
            ])->values();
    }
}
