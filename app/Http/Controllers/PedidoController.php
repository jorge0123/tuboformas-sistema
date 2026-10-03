<?php

namespace App\Http\Controllers;

use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\Maquina;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\ViajeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    public function __construct(private PedidoService $pedidos) {}

    public function index(Request $request)
    {
        $u = $request->user();
        abort_unless($u->canAny(['pedidos.ver', 'pedidos.ver_todos', 'pedidos.preparar']) || $u->es_piloto, 403);
        $vista = $request->input('ver', 'activos');

        $base = Pedido::visiblesPara($u)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('folio', 'like', '%'.$request->q.'%')
                ->orWhere('orden_compra', 'like', '%'.$request->q.'%')->orWhereHas('cliente', fn ($c) => $c->buscar($request->q))))
            ->when($request->input('cuando') === 'hoy', fn ($q) => $q->whereDate('fecha_entrega', today()))
            ->when($request->input('cuando') === 'manana', fn ($q) => $q->whereDate('fecha_entrega', today()->addDay()))
            ->when($request->input('cuando') === 'semana', fn ($q) => $q->whereBetween('fecha_entrega', [today(), today()->addDays(6)]))
            ->when($request->input('cuando') === 'atrasados', fn ($q) => $q->activos()->whereDate('fecha_entrega', '<', today()))
            ->when($request->filled('vendedor'), fn ($q) => $q->where('vendedor_id', $request->vendedor));

        $conteos = (clone $base)->selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado');
        $pedidos = (clone $base)->with(['cliente', 'vendedor', 'preparador'])->withCount(['lineas', 'lineas as armadas' => fn ($q) => $q->where('preparada', true)])
            ->when($vista === 'activos', fn ($q) => $q->activos(), fn ($q) => $q->when($vista !== 'todos', fn ($w) => $w->where('estado', $vista)))
            // Lo urgente y lo que vence primero, arriba.
            ->orderByRaw("case when prioridad = 'urgente' then 0 else 1 end")
            ->orderBy(in_array($vista, ['entregado', 'cancelado', 'todos']) ? 'updated_at' : 'fecha_entrega', in_array($vista, ['entregado', 'cancelado', 'todos']) ? 'desc' : 'asc')
            ->orderBy('id')->paginate(30)->withQueryString();

        $vendedores = $u->canAny(['pedidos.ver_todos', 'pedidos.preparar'])
            ? User::whereIn('id', Pedido::select('vendedor_id')->distinct())->orderBy('name')->get(['id', 'name']) : collect();

        return view('pedidos.index', compact('pedidos', 'conteos', 'vista', 'vendedores'));
    }

    public function create(Request $request)
    {
        $this->authorize('pedidos.crear');

        return view('pedidos.form', $this->datos(new Pedido([
            'fecha_entrega' => today()->addDay()->isSunday() ? today()->addDays(2) : today()->addDay(),
            'tipo_entrega' => 'ruta', 'prioridad' => 'normal', 'condicion_pago' => 'contado',
            'cliente_id' => $request->integer('cliente') ?: null,
        ])));
    }

    public function store(Request $request)
    {
        $this->authorize('pedidos.crear');
        [$datos, $lineas] = $this->validar($request);
        $pedido = $this->pedidos->crear($datos, $lineas, $request->user());

        return redirect()->route('pedidos.show', $pedido)->with('ok', "{$pedido->folio} ingresado. Bodega ya recibió el aviso.");
    }

    public function show(Request $request, Pedido $pedido)
    {
        $this->puedeVer($request->user(), $pedido);
        $pedido->load(['cliente', 'vendedor', 'preparador', 'despachador', 'movimiento', 'bodega', 'vehiculoAsignado', 'pilotoAsignado', 'viaje',
            'lineas.producto.unidad', 'lineas.producto.existencias', 'lineas.presentacion', 'seguimientos.user', 'archivos.user']);

        return view('pedidos.show', [
            'p' => $pedido,
            'permisos' => $this->permisos($request->user(), $pedido),
            // Flota propia (Máquinas → área Vehículos) y usuarios marcados como pilotos.
            'vehiculos' => Maquina::whereHas('area', fn ($q) => $q->where('nombre', 'Vehículos'))->whereNotIn('estado', ['baja'])->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'marca', 'estado']),
            'pilotos' => User::activos()->where('es_piloto', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, Pedido $pedido)
    {
        abort_unless($this->permisos($request->user(), $pedido)['editar'], 403);
        $pedido->load('lineas');

        return view('pedidos.form', $this->datos($pedido));
    }

    public function update(Request $request, Pedido $pedido)
    {
        abort_unless($this->permisos($request->user(), $pedido)['editar'], 403);
        [$datos, $lineas] = $this->validar($request);
        $this->pedidos->actualizar($pedido, $datos, $lineas, $request->user());

        return redirect()->route('pedidos.show', $pedido)->with('ok', 'Pedido actualizado. Bodega recibió el aviso del cambio.');
    }

    public function tomar(Request $request, Pedido $pedido)
    {
        $this->authorize('pedidos.preparar');
        $this->pedidos->tomar($pedido, $request->user());

        return back()->with('ok', 'El pedido quedó a tu nombre. Marca cada producto conforme lo armes.');
    }

    /** Marca una línea como armada (JSON: se usa mientras se arma, sin recargar). */
    public function linea(Request $request, Pedido $pedido, PedidoLinea $linea)
    {
        $this->authorize('pedidos.preparar');
        abort_unless($linea->pedido_id === $pedido->id, 404);
        $d = $request->validate(['preparada' => ['required', 'boolean'], 'cantidad' => ['nullable', 'numeric', 'min:0']]);
        $this->pedidos->marcarLinea($linea, (bool) $d['preparada'], isset($d['cantidad']) ? (float) $d['cantidad'] : null, $request->user());
        $pedido->refresh();

        return response()->json(['ok' => true, 'estado' => $pedido->estado, 'armadas' => $pedido->lineas()->where('preparada', true)->count(), 'total' => $pedido->lineas()->count()]);
    }

    public function listo(Request $request, Pedido $pedido)
    {
        $this->authorize('pedidos.preparar');
        $this->pedidos->marcarListo($pedido, $request->user(), $request->input('nota'));

        return back()->with('ok', "{$pedido->folio} listo. Se avisó a {$pedido->vendedor->name}.");
    }

    public function despachar(Request $request, Pedido $pedido)
    {
        $this->authorize('pedidos.preparar');
        $propio = $pedido->tipo_entrega === 'ruta';
        $d = $request->validate([
            'vehiculo_id' => [Rule::requiredIf($propio), 'nullable', 'exists:maquinas,id'],
            'piloto_id' => [Rule::requiredIf($propio), 'nullable', Rule::exists('users', 'id')->where('es_piloto', true)],
            'empresa' => [Rule::requiredIf(! $propio), 'nullable', 'string', 'max:60'],
            'guia' => ['nullable', 'string', 'max:60'],
        ], [], ['vehiculo_id' => 'vehículo', 'piloto_id' => 'piloto', 'empresa' => 'empresa de transporte', 'guia' => 'número de guía']);
        $this->pedidos->despachar($pedido, $d, $request->user());

        return back()->with('ok', "{$pedido->folio} en ruta. El inventario ya se descontó.");
    }

    public function entregar(Request $request, Pedido $pedido)
    {
        abort_unless($this->permisos($request->user(), $pedido)['entregar'], 403);
        $d = $request->validate(['recibido_por' => ['required', 'string', 'max:100'], 'nota' => ['nullable', 'string', 'max:500']],
            [], ['recibido_por' => 'quién recibió']);
        // Si va en un viaje, se entrega como parada (así el viaje se cierra al terminar).
        $pedido->viaje_id && $pedido->estado === 'en_ruta'
            ? app(ViajeService::class)->entregar($pedido, $d, $request->user())
            : $this->pedidos->entregar($pedido, $d, $request->user());

        return back()->with('ok', "{$pedido->folio} entregado.");
    }

    public function cancelar(Request $request, Pedido $pedido)
    {
        abort_unless($this->permisos($request->user(), $pedido)['cancelar'], 403);
        $d = $request->validate(['motivo' => ['required', 'string', 'max:255']]);
        $this->pedidos->cancelar($pedido, $d['motivo'], $request->user());

        return back()->with('ok', "{$pedido->folio} cancelado.");
    }

    public function comentar(Request $request, Pedido $pedido)
    {
        $this->puedeVer($request->user(), $pedido);
        $d = $request->validate(['texto' => ['required', 'string', 'max:1000']]);
        $this->pedidos->comentar($pedido, $d['texto'], $request->user());

        return back()->with('ok', 'Comentario agregado.');
    }

    // ── Internos ─────────────────────────────────────────────────────────

    private function puedeVer(User $u, Pedido $p): void
    {
        abort_unless($u->canAny(['pedidos.ver_todos', 'pedidos.preparar', 'pedidos.gestionar']) || ($u->can('pedidos.ver') && $p->vendedor_id === $u->id) || $p->piloto_id === $u->id, 403);
    }

    private function permisos(User $u, Pedido $p): array
    {
        $propio = $p->vendedor_id === $u->id && $u->can('pedidos.crear');

        return [
            'editar' => $p->estado === 'nuevo' && ($propio || $u->can('pedidos.gestionar')),
            'cancelar' => in_array($p->estado, ['nuevo', 'preparando', 'listo'], true) && ($propio || $u->can('pedidos.gestionar')),
            'preparar' => $u->can('pedidos.preparar'),
            // Bodega o el piloto que lleva el pedido confirman la entrega.
            'entregar' => $u->can('pedidos.preparar') || ($p->piloto_id && $p->piloto_id === $u->id),
        ];
    }

    private function datos(Pedido $p): array
    {
        $bodegaPt = Bodega::where('codigo', 'PT')->value('id');

        return [
            'p' => $p,
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'nit', 'contacto', 'telefono', 'direccion', 'municipio']),
            'bodegas' => Bodega::where('activo', true)->orderBy('id')->get(['id', 'nombre']),
            'bodegaInicial' => $p->bodega_id ?? $bodegaPt,
            'productos' => Producto::where('activo', true)->where('tipo', 'producto_terminado')->with(['unidad', 'presentaciones', 'existencias'])
                ->orderBy('nombre')->orderBy('color')->get()->map(fn ($x) => [
                    'id' => $x->id, 'codigo' => $x->codigo, 'nombre' => $x->nombre.($x->color ? ' · '.$x->color : ''), 'color' => $x->color,
                    'unidad' => $x->unidad->abreviatura,
                    'presentaciones' => $x->presentaciones->map(fn ($pr) => ['id' => $pr->id, 'nombre' => $pr->nombre, 'factor' => (float) $pr->factor])->values(),
                    'stock' => $x->existencias->mapWithKeys(fn ($e) => [$e->bodega_id => (float) $e->cantidad]),
                ])->values(),
        ];
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'fecha_entrega' => ['required', 'date'],
            'jornada' => ['nullable', Rule::in(array_keys(Pedido::JORNADAS))],
            'tipo_entrega' => ['required', Rule::in(array_keys(Pedido::TIPOS_ENTREGA))],
            'prioridad' => ['required', Rule::in(['normal', 'urgente'])],
            'direccion_entrega' => ['nullable', 'required_unless:tipo_entrega,recoge', 'string', 'max:255'],
            'contacto_nombre' => ['nullable', 'string', 'max:100'],
            'contacto_telefono' => ['nullable', 'string', 'max:50'],
            'orden_compra' => ['nullable', 'string', 'max:60'],
            'condicion_pago' => ['nullable', Rule::in(array_keys(Pedido::CONDICIONES_PAGO))],
            'notas' => ['nullable', 'string', 'max:2000'],
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'exists:productos,id'],
            'lineas.*.presentacion_id' => ['nullable', 'exists:producto_presentaciones,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.notas' => ['nullable', 'string', 'max:255'],
        ], [], [
            'cliente_id' => 'cliente', 'fecha_entrega' => 'fecha de entrega', 'direccion_entrega' => 'dirección de entrega',
            'lineas' => 'productos', 'lineas.*.cantidad' => 'cantidad', 'lineas.*.producto_id' => 'producto',
        ]);
        $lineas = $d['lineas'];
        unset($d['lineas']);

        return [$d, $lineas];
    }
}
