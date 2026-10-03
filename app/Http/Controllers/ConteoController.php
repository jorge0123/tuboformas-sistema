<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\CategoriaProducto;
use App\Models\Conteo;
use App\Models\ConteoLinea;
use App\Models\Producto;
use App\Models\User;
use App\Notifications\Aviso;
use App\Services\Folios;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Conteo físico de la bodega de repuestos: toma la existencia del sistema al abrirse,
 * se captura lo contado y al aplicar las diferencias se vuelven ajustes aprobados.
 */
class ConteoController extends Controller
{
    public function index()
    {
        $this->authorize('conteos.ver');
        $conteos = Conteo::with('user')->withCount([
            'lineas',
            'lineas as contadas' => fn ($q) => $q->whereNotNull('cantidad_contada'),
        ])->latest()->paginate(25);

        return view('conteos.index', compact('conteos'));
    }

    public function create()
    {
        $this->authorize('conteos.gestionar');

        return view('conteos.create', [
            'categorias' => CategoriaProducto::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('conteos.gestionar');
        $d = $request->validate([
            'tipo' => ['nullable', Rule::in(array_keys(Producto::TIPOS))],
            'categoria_id' => ['nullable', 'exists:categorias_producto,id'],
            'solo_con_existencia' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);
        if (Conteo::where('estado', 'abierto')->exists()) {
            return back()->with('error', 'Ya hay un conteo abierto. Aplícalo o cancélalo primero.');
        }

        $conteo = DB::transaction(function () use ($d, $request) {
            $conteo = Conteo::create([
                'folio' => Folios::siguiente('CNT', 5), 'fecha' => today(),
                'notas' => $d['notas'] ?? null, 'user_id' => $request->user()->id,
            ]);
            Producto::where('activo', true)
                ->when($d['tipo'] ?? null, fn ($q, $t) => $q->where('tipo', $t))
                ->when($d['categoria_id'] ?? null, fn ($q, $c) => $q->where('categoria_id', $c))
                ->when(! empty($d['solo_con_existencia']), fn ($q) => $q->where('existencia', '>', 0))
                ->orderBy('nombre')->get()
                ->each(fn ($p) => $conteo->lineas()->create(['producto_id' => $p->id, 'cantidad_sistema' => $p->existencia]));

            return $conteo;
        });
        Auditoria::registrar('crear', $conteo, $conteo->folio);
        Notification::send(
            User::activos()->permission('conteos.registrar')->where('id', '!=', $request->user()->id)->get(),
            new Aviso('Conteo físico abierto', "{$conteo->folio}: ya pueden empezar a contar.", route('conteos.show', $conteo))
        );

        return redirect()->route('conteos.show', $conteo)->with('ok', "Conteo {$conteo->folio} abierto con {$conteo->lineas()->count()} repuestos.");
    }

    public function show(Request $request, Conteo $conteo)
    {
        $this->authorize('conteos.ver');
        $conteo->load(['user', 'aplicadoPor']);
        $lineas = $conteo->lineas()->with('producto.unidad')
            ->when($request->filled('q'), fn ($q) => $q->whereHas('producto', fn ($p) => $p->buscar($request->q)))
            ->when($request->input('ver') === 'pendientes', fn ($q) => $q->whereNull('cantidad_contada'))
            ->when($request->input('ver') === 'diferencias', fn ($q) => $q->whereNotNull('cantidad_contada')->whereColumn('cantidad_contada', '!=', 'cantidad_sistema'))
            ->get()->sortBy('producto.nombre');
        $resumen = [
            'total' => $conteo->lineas()->count(),
            'contadas' => $conteo->lineas()->whereNotNull('cantidad_contada')->count(),
            'diferencias' => $conteo->lineas()->whereNotNull('cantidad_contada')->whereColumn('cantidad_contada', '!=', 'cantidad_sistema')->count(),
        ];
        // Mostrar lo que dice el sistema puede sesgar al que cuenta: solo lo ve quien gestiona.
        $verSistema = $request->user()->can('conteos.gestionar') || $conteo->estado !== 'abierto';

        return view('conteos.show', compact('conteo', 'lineas', 'resumen', 'verSistema'));
    }

    /** Guarda lo contado (una o varias líneas). Responde JSON para guardar mientras se cuenta. */
    public function capturar(Request $request, Conteo $conteo)
    {
        $this->authorize('conteos.registrar');
        abort_unless($conteo->estado === 'abierto', 422, 'El conteo ya está cerrado.');
        $d = $request->validate([
            'linea_id' => ['required', 'integer'],
            'cantidad' => ['nullable', 'numeric', 'min:0'],
        ]);
        $linea = ConteoLinea::where('conteo_id', $conteo->id)->findOrFail($d['linea_id']);
        $linea->update([
            'cantidad_contada' => $d['cantidad'],
            'contado_por' => $d['cantidad'] === null ? null : $request->user()->id,
            'contado_at' => $d['cantidad'] === null ? null : now(),
        ]);

        return response()->json(['ok' => true, 'diferencia' => $linea->diferencia()]);
    }

    public function aplicar(Request $request, Conteo $conteo, InventarioService $inv)
    {
        $this->authorize('conteos.gestionar');
        abort_unless($conteo->estado === 'abierto', 422);

        $lineas = $conteo->lineas()->whereNotNull('cantidad_contada')->get();
        // Se compara contra la existencia ACTUAL, por si hubo movimientos mientras se contaba.
        $actual = Producto::whereIn('id', $lineas->pluck('producto_id'))->pluck('existencia', 'id');
        $entradas = [];
        $salidas = [];
        foreach ($lineas as $l) {
            $dif = round((float) $l->cantidad_contada - (float) ($actual[$l->producto_id] ?? 0), 3);
            if ($dif > 0) {
                $entradas[] = ['producto_id' => $l->producto_id, 'cantidad' => $dif];
            } elseif ($dif < 0) {
                $salidas[] = ['producto_id' => $l->producto_id, 'cantidad' => abs($dif)];
            }
        }

        DB::transaction(function () use ($conteo, $entradas, $salidas, $inv, $request) {
            // El usuario tiene conteos.gestionar; los ajustes de un conteo se aplican aprobados.
            $u = $request->user();
            $nota = "Ajuste por conteo físico {$conteo->folio}";
            if ($salidas) {
                $this->ajuste($inv, 'ajuste_salida', $salidas, $u, $nota);
            }
            if ($entradas) {
                $this->ajuste($inv, 'ajuste_entrada', $entradas, $u, $nota);
            }
            $conteo->update(['estado' => 'aplicado', 'aplicado_por' => $u->id, 'aplicado_at' => now()]);
        });
        Auditoria::registrar('aplicar', $conteo, $conteo->folio.': '.count($entradas).' entradas, '.count($salidas).' salidas');
        // Quienes contaron se enteran de que su conteo ya se aplicó.
        Notification::send(
            User::whereIn('id', $conteo->lineas()->whereNotNull('contado_por')->distinct()->pluck('contado_por'))
                ->where('id', '!=', $request->user()->id)->activos()->get(),
            new Aviso('Conteo aplicado', "{$conteo->folio}: ".(count($entradas) + count($salidas)).' repuestos ajustados.', route('conteos.show', $conteo))
        );

        return back()->with('ok', 'Conteo aplicado. '.(count($entradas) + count($salidas)).' repuestos ajustados.');
    }

    public function cancelar(Conteo $conteo)
    {
        $this->authorize('conteos.gestionar');
        abort_unless($conteo->estado === 'abierto', 422);
        $conteo->update(['estado' => 'cancelado']);
        Auditoria::registrar('cancelar', $conteo, $conteo->folio);

        return back()->with('ok', 'Conteo cancelado. No se modificó la existencia.');
    }

    private function ajuste(InventarioService $inv, string $tipo, array $lineas, $u, string $nota): void
    {
        $mov = $inv->registrar(['tipo' => $tipo, 'fecha' => today(), 'notas' => $nota, 'referencia' => $nota], $lineas, $u);
        if ($mov->estado === 'pendiente') {
            $inv->aprobar($mov, $u);
        }
    }
}
