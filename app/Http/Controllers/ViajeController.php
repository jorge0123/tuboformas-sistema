<?php

namespace App\Http\Controllers;

use App\Models\Maquina;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Viaje;
use App\Services\ViajeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ViajeController extends Controller
{
    public function __construct(private ViajeService $viajes) {}

    public function index(Request $request)
    {
        $u = $request->user();
        abort_unless($u->can('pedidos.preparar') || $u->es_piloto, 403);
        $base = Viaje::with(['vehiculo', 'piloto'])->withCount(['pedidos', 'pedidos as entregados' => fn ($q) => $q->where('estado', 'entregado')])
            // El piloto que no es de bodega ve solo los suyos.
            ->when(! $u->can('pedidos.preparar'), fn ($q) => $q->where('piloto_id', $u->id));
        $enRuta = (clone $base)->where('estado', 'en_ruta')->latest('salida_at')->get();
        $terminados = (clone $base)->where('estado', 'terminado')->latest('salida_at')->paginate(20);
        $listos = Pedido::where('estado', 'listo')->where('tipo_entrega', 'ruta')->count();

        return view('viajes.index', compact('enRuta', 'terminados', 'listos'));
    }

    public function create(Request $request)
    {
        $this->authorize('pedidos.preparar');
        $pedidos = Pedido::with(['cliente'])->withCount('lineas')->where('estado', 'listo')->where('tipo_entrega', 'ruta')
            ->orderByRaw("case when prioridad = 'urgente' then 0 else 1 end")->orderBy('fecha_entrega')->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'folio' => $p->folio, 'cliente' => $p->cliente->nombre,
                'municipio' => $p->cliente->municipio ?: 'Sin municipio', 'direccion' => $p->direccion_entrega,
                'fecha' => $p->fecha_entrega->translatedFormat('D d M'), 'atrasado' => $p->atrasado(), 'hoy' => $p->fecha_entrega->isToday(),
                'urgente' => $p->prioridad === 'urgente', 'productos' => $p->lineas_count, 'jornada' => $p->jornada,
            ])->values();
        $vehiculos = Maquina::whereHas('area', fn ($q) => $q->where('nombre', 'Vehículos'))->where('estado', '!=', 'baja')->orderBy('codigo')->get()
            ->map(fn ($v) => ['id' => $v->id, 'codigo' => $v->codigo, 'nombre' => $v->nombre, 'marca' => $v->marca, 'estado' => $v->estado,
                'viaje' => Viaje::where('vehiculo_id', $v->id)->where('estado', 'en_ruta')->value('folio')]);
        $pilotos = User::activos()->where('es_piloto', true)->orderBy('name')->get()
            ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->name, 'iniciales' => $p->iniciales(),
                'viaje' => Viaje::where('piloto_id', $p->id)->where('estado', 'en_ruta')->value('folio')]);

        return view('viajes.create', [
            'pedidos' => $pedidos, 'vehiculos' => $vehiculos, 'pilotos' => $pilotos,
            'preseleccion' => array_map('intval', (array) $request->input('pedidos', [])),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('pedidos.preparar');
        $d = $request->validate([
            'vehiculo_id' => ['required', 'exists:maquinas,id'],
            'piloto_id' => ['required', Rule::exists('users', 'id')->where('es_piloto', true)],
            'pedidos' => ['required', 'array', 'min:1'],
            'pedidos.*' => ['integer', 'exists:pedidos,id'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], ['pedidos.required' => 'Elige los pedidos que van en el viaje.'], ['vehiculo_id' => 'vehículo', 'piloto_id' => 'piloto']);
        $viaje = $this->viajes->salir($d['vehiculo_id'], $d['piloto_id'], $d['pedidos'], $d['notas'] ?? null, $request->user());

        return redirect()->route('viajes.show', $viaje)->with('ok', "{$viaje->folio} salió con ".count($d['pedidos']).' pedidos. Se avisó al piloto y a ventas.');
    }

    public function show(Request $request, Viaje $viaje)
    {
        $this->puedeVer($request->user(), $viaje);
        $viaje->load(['vehiculo', 'piloto', 'despachador', 'pedidos.cliente', 'pedidos.vendedor', 'pedidos.lineas.producto.unidad', 'pedidos.lineas.presentacion']);

        return view('viajes.show', ['v' => $viaje, 'puedeEntregar' => $this->puedeEntregar($request->user(), $viaje)]);
    }

    public function entregar(Request $request, Viaje $viaje, Pedido $pedido)
    {
        abort_unless($pedido->viaje_id === $viaje->id && $this->puedeEntregar($request->user(), $viaje), 403);
        $d = $request->validate(['recibido_por' => ['required', 'string', 'max:100'], 'nota' => ['nullable', 'string', 'max:500']], [], ['recibido_por' => 'quién recibió']);
        $this->viajes->entregar($pedido, $d, $request->user());

        return back()->with('ok', "Parada {$pedido->orden_parada} entregada: {$pedido->cliente->nombre}.");
    }

    public function noEntregado(Request $request, Viaje $viaje, Pedido $pedido)
    {
        abort_unless($pedido->viaje_id === $viaje->id && $this->puedeEntregar($request->user(), $viaje), 403);
        $d = $request->validate(['motivo' => ['required', 'string', 'max:255']]);
        $this->viajes->noEntregado($pedido, $d['motivo'], $request->user());

        return back()->with('ok', "{$pedido->folio} regresa a bodega. Se avisó a ventas.");
    }

    private function puedeVer(User $u, Viaje $v): void
    {
        abort_unless($u->can('pedidos.preparar') || $v->piloto_id === $u->id, 403);
    }

    private function puedeEntregar(User $u, Viaje $v): bool
    {
        return $v->estado === 'en_ruta' && ($v->piloto_id === $u->id || $u->can('pedidos.preparar'));
    }
}
