<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Especialidad;
use App\Models\Maquina;
use App\Models\OrdenTrabajo;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\AsistenciaService;
use App\Services\InventarioService;
use App\Services\OrdenTrabajoService;
use App\Support\Csv;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrdenTrabajoController extends Controller
{
    public function __construct(private OrdenTrabajoService $ots, private AsistenciaService $asistencia) {}

    public function index(Request $request)
    {
        $this->autorizarAlguno(['ot.ver_todas', 'ot.ver_propias']);
        $ordenes = $this->filtrar($request)
            ->with(['maquina', 'responsable', 'especialidad'])
            ->orderByRaw("FIELD(estado, 'en_progreso', 'en_espera', 'pendiente', 'completada', 'cancelada')")
            ->orderByRaw('fecha_vencimiento is null, fecha_vencimiento')
            ->paginate(25)->withQueryString();

        return view('ot.index', array_merge(compact('ordenes'), $this->catalogos()));
    }

    public function kanban(Request $request)
    {
        $this->autorizarAlguno(['ot.ver_todas', 'ot.ver_propias']);
        $ordenes = $this->filtrar($request, false)
            ->where(fn ($q) => $q->whereIn('estado', OrdenTrabajo::ABIERTOS)
                ->orWhere(fn ($w) => $w->where('estado', 'completada')->where('completada_at', '>=', now()->subDays(14))))
            ->with(['maquina', 'responsable', 'especialidad'])
            ->withCount('seguimientos')
            ->orderBy('orden_kanban')->orderByRaw('fecha_vencimiento is null, fecha_vencimiento')
            ->get()->groupBy('estado');

        return view('ot.kanban', array_merge(compact('ordenes'), $this->catalogos()));
    }

    public function exportar(Request $request)
    {
        $this->autorizarAlguno(['ot.ver_todas', 'ot.ver_propias']);
        $filas = $this->filtrar($request)->with(['maquina', 'responsable', 'especialidad'])->orderBy('id')->get()->map(fn ($o) => [
            $o->folio, $o->titulo, $o->maquina?->etiqueta(), OrdenTrabajo::TIPOS[$o->tipo], $o->especialidad?->nombre,
            $o->responsable?->name, OrdenTrabajo::PRIORIDADES[$o->prioridad], $o->fecha_inicio?->format('d/m/Y'),
            $o->fecha_vencimiento?->format('d/m/Y'), OrdenTrabajo::SITUACIONES[$o->situacion()], $o->progreso.'%',
            $o->horas_trabajo, OrdenTrabajo::ESTADOS[$o->estado], $o->completada_at?->format('d/m/Y'), $o->motivo_espera,
        ]);

        return Csv::descargar('ordenes-de-trabajo', ['Folio', 'Tarea', 'Máquina', 'Tipo', 'Especialidad', 'Responsable', 'Prioridad',
            'Inicio', 'Vencimiento', 'Situación', 'Progreso', 'Horas', 'Estado', 'Completada', 'En espera por'], $filas);
    }

    public function create(Request $request)
    {
        $this->authorize('ot.crear');
        $ot = new OrdenTrabajo([
            'maquina_id' => $request->integer('maquina') ?: null,
            'tipo' => $request->user()->can('ot.asignar') ? 'correctivo' : 'correctivo',
            'prioridad' => 'media',
            'fecha_inicio' => $request->date('fecha') ?? today(),
            'fecha_vencimiento' => $request->date('fecha') ?? today()->addDays(7),
        ]);

        return view('ot.form', array_merge(['ot' => $ot], $this->catalogos()));
    }

    public function store(Request $request)
    {
        $this->authorize('ot.crear');
        $datos = $this->validar($request);
        $ot = $this->ots->crear($datos, $request->user());

        return redirect()->route('ot.show', $ot)->with('ok', "Orden {$ot->folio} creada.".($ot->responsable ? " Se notificó a {$ot->responsable->name}." : ''));
    }

    public function show(Request $request, OrdenTrabajo $ot)
    {
        $this->puedeVer($request->user(), $ot);
        $ot->load(['maquina.area', 'responsable.especialidad', 'solicitante', 'ayudantes', 'especialidad', 'plan',
            'seguimientos.user', 'archivos.user', 'bitacora', 'movimientos.lineas.producto.unidad', 'tramos.user']);

        $u = $request->user();
        $permisos = [
            'ejecutar' => $this->puedeEjecutar($u, $ot),
            'editar' => $u->can('ot.editar'),
            'cancelar' => $u->can('ot.cancelar') && $ot->estaAbierta(),
            'comentar' => true,
        ];
        $repuestos = $permisos['ejecutar'] && $ot->estaAbierta()
            ? Producto::where('activo', true)->where('existencia', '>', 0)->with('unidad')->orderBy('nombre')->get()
            : collect();
        $proveedores = Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('ot.show', compact('ot', 'permisos', 'repuestos', 'proveedores'));
    }

    public function edit(OrdenTrabajo $ot)
    {
        $this->authorize('ot.editar');
        $ot->load('ayudantes');

        return view('ot.form', array_merge(['ot' => $ot], $this->catalogos()));
    }

    public function update(Request $request, OrdenTrabajo $ot)
    {
        $this->authorize('ot.editar');
        $this->ots->actualizar($ot, $this->validar($request), $request->user());

        return redirect()->route('ot.show', $ot)->with('ok', 'Cambios guardados.');
    }

    public function destroy(OrdenTrabajo $ot)
    {
        $this->authorize('ot.eliminar');
        if ($ot->bitacora || $ot->movimientos()->exists()) {
            return back()->with('error', 'La orden ya está en la bitácora o tiene repuestos consumidos. Cancélala en lugar de eliminarla.');
        }
        Auditoria::registrar('eliminar', $ot, "{$ot->folio} · {$ot->titulo}");
        $ot->delete();

        return redirect()->route('ot.index')->with('ok', 'Orden eliminada.');
    }

    /** El técnico registra avance: nota, % de avance, estado y horas. */
    public function seguimiento(Request $request, OrdenTrabajo $ot)
    {
        $this->autorizarEjecucion($request->user(), $ot);
        $d = $request->validate([
            'texto' => ['nullable', 'string', 'max:3000'],
            'progreso' => ['required', 'integer', 'between:0,100'],
            'estado' => ['required', Rule::in(OrdenTrabajo::ABIERTOS)],
            'horas' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'motivo_espera' => ['nullable', 'string', 'max:255'],
        ]);
        $this->ots->registrarSeguimiento($ot, $d, $request->user());

        return back()->with('ok', 'Avance registrado.');
    }

    public function comentario(Request $request, OrdenTrabajo $ot)
    {
        $this->puedeVer($request->user(), $ot);
        $d = $request->validate(['texto' => ['required', 'string', 'max:3000']]);
        $this->ots->comentar($ot, $d['texto'], $request->user());

        return back()->with('ok', 'Comentario publicado.');
    }

    public function checklist(Request $request, OrdenTrabajo $ot)
    {
        $this->autorizarEjecucion($request->user(), $ot);
        $d = $request->validate(['indice' => ['required', 'integer', 'min:0'], 'hecho' => ['required', 'boolean']]);
        $lista = $ot->checklist ?? [];
        abort_unless(isset($lista[$d['indice']]), 422);
        $lista[$d['indice']]['hecho'] = $d['hecho'];
        $ot->update(['checklist' => $lista]);

        return response()->json(['ok' => true]);
    }

    public function completar(Request $request, OrdenTrabajo $ot)
    {
        $this->autorizarEjecucion($request->user(), $ot);
        $d = $request->validate([
            'trabajo_realizado' => ['required', 'string', 'max:5000'],
            'componente' => ['nullable', 'string', 'max:100'],
            'horas' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'detuvo_maquina' => ['nullable', 'boolean'],
            'horas_paro' => ['nullable', 'numeric', 'min:0'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'garantia' => ['nullable', 'boolean'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'horometro' => ['nullable', 'numeric', 'min:0'],
            'comentarios' => ['nullable', 'string', 'max:2000'],
        ], [], ['trabajo_realizado' => 'trabajo realizado']);
        $this->ots->completar($ot, $d, $request->user());

        return redirect()->route('ot.show', $ot)->with('ok', $ot->maquina_id
            ? 'Orden completada y registrada en la bitácora de la máquina.'
            : 'Orden completada.');
    }

    public function cancelar(Request $request, OrdenTrabajo $ot)
    {
        $this->authorize('ot.cancelar');
        $d = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $this->ots->cancelar($ot, $d['motivo'], $request->user());

        return back()->with('ok', 'Orden cancelada.');
    }

    /** Arrastrar en el Kanban. Solo entre estados abiertos; completar pide el trabajo realizado. */
    public function mover(Request $request, OrdenTrabajo $ot)
    {
        $this->autorizarEjecucion($request->user(), $ot);
        $d = $request->validate([
            'estado' => ['required', Rule::in(OrdenTrabajo::ABIERTOS)],
            'motivo_espera' => ['nullable', 'string', 'max:255'],
        ]);
        if ($d['estado'] !== $ot->estado) {
            $this->ots->registrarSeguimiento($ot, ['estado' => $d['estado'], 'motivo_espera' => $d['motivo_espera'] ?? null], $request->user());
        }

        return response()->json(['ok' => true, 'estado' => $ot->fresh()->estado]);
    }

    /** "Trabajar en esta orden": empieza a contar su tiempo (y la pasa a en progreso si hacía falta). */
    public function trabajar(Request $request, OrdenTrabajo $ot)
    {
        $u = $request->user();
        $this->autorizarEjecucion($u, $ot);
        abort_unless($u->marcaAsistencia(), 422, 'El tiempo por orden es para quien marca asistencia.');
        if ($ot->estado !== 'en_progreso') {
            $this->ots->registrarSeguimiento($ot, ['estado' => 'en_progreso', 'texto' => null], $u);
        } else {
            $this->asistencia->iniciarTrabajo($ot, $u);
        }

        return back()->with('ok', 'Tu tiempo en '.$ot->folio.' está corriendo.');
    }

    public function pausar(Request $request, OrdenTrabajo $ot)
    {
        $this->autorizarEjecucion($request->user(), $ot);
        $this->asistencia->pausarTrabajo($ot, $request->user());

        return back()->with('ok', 'Tiempo pausado. La orden sigue en progreso.');
    }

    /** Repuestos usados: salen de la bodega de repuestos como consumo de mantenimiento ligado a la OT. */
    public function repuestos(Request $request, OrdenTrabajo $ot, InventarioService $inv)
    {
        $this->autorizarEjecucion($request->user(), $ot);
        $d = $request->validate([
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', 'exists:productos,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ]);
        $mov = $inv->registrar([
            'tipo' => 'consumo_mantenimiento',
            'maquina_id' => $ot->maquina_id, 'orden_trabajo_id' => $ot->id, 'referencia' => $ot->folio.' · '.$ot->titulo,
        ], $d['lineas'], $request->user());
        $ot->seguimientos()->create([
            'user_id' => $request->user()->id, 'tipo' => 'avance',
            'texto' => 'Repuestos usados ('.$mov->folio.'): '.$mov->lineas()->with('producto')->get()
                ->map(fn ($l) => Formato::numero($l->cantidad).' × '.$l->producto->nombre)->join(', '),
        ]);

        return back()->with('ok', "Repuestos descontados de la bodega ({$mov->folio}).");
    }

    // ── Apoyo ──────────────────────────────────────────────────────────

    private function filtrar(Request $request, bool $conEstado = true)
    {
        $u = $request->user();

        return OrdenTrabajo::visiblesPara($u)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('titulo', 'like', '%'.$request->q.'%')
                ->orWhere('folio', 'like', '%'.$request->q.'%')
                ->orWhereHas('maquina', fn ($m) => $m->buscar($request->q))))
            ->when($conEstado && $request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($conEstado && ! $request->filled('estado') && ! $request->filled('situacion') && ! $request->boolean('todas'),
                fn ($q) => $q->whereIn('estado', OrdenTrabajo::ABIERTOS))
            ->when($request->filled('situacion'), fn ($q) => $q->enSituacion($request->situacion))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('prioridad'), fn ($q) => $q->where('prioridad', $request->prioridad))
            ->when($request->filled('especialidad'), fn ($q) => $q->where('especialidad_id', $request->especialidad))
            ->when($request->filled('responsable'), fn ($q) => $q->where('responsable_id', $request->responsable))
            ->when($request->filled('maquina'), fn ($q) => $q->where('maquina_id', $request->maquina))
            ->when($request->boolean('sin_responsable'), fn ($q) => $q->whereNull('responsable_id'))
            ->when($request->boolean('mias'), fn ($q) => $q->where(fn ($w) => $w->where('responsable_id', $u->id)
                ->orWhereHas('ayudantes', fn ($a) => $a->where('users.id', $u->id))));
    }

    private function catalogos(): array
    {
        return [
            'maquinas' => Maquina::where('estado', '!=', 'baja')->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'especialidades' => Especialidad::where('activo', true)->orderBy('nombre')->get(),
            'tecnicos' => User::asignables()->with('especialidad')->get(),
        ];
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'maquina_id' => ['nullable', 'exists:maquinas,id'],
            'tipo' => ['required', Rule::in(array_keys(OrdenTrabajo::TIPOS))],
            'especialidad_id' => ['nullable', 'exists:especialidades,id'],
            'prioridad' => ['required', Rule::in(array_keys(OrdenTrabajo::PRIORIDADES))],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'ayudantes' => ['nullable', 'array'],
            'ayudantes.*' => ['integer', 'exists:users,id'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.texto' => ['nullable', 'string', 'max:255'],
            'checklist.*.hecho' => ['nullable', 'boolean'],
        ], [], ['titulo' => 'título', 'fecha_vencimiento' => 'fecha de vencimiento']);

        // Quien no puede asignar solo reporta: la OT queda sin responsable para que la asigne un coordinador.
        if (! $request->user()->can('ot.asignar')) {
            unset($d['responsable_id'], $d['ayudantes']);
        } else {
            $d['ayudantes'] = array_values(array_diff($d['ayudantes'] ?? [], [$d['responsable_id'] ?? null]));
        }
        $d['checklist'] = collect($d['checklist'] ?? [])->filter(fn ($c) => filled($c['texto'] ?? null))
            ->map(fn ($c) => ['texto' => trim($c['texto']), 'hecho' => (bool) ($c['hecho'] ?? false)])->values()->all() ?: null;

        return $d;
    }

    private function puedeVer(User $u, OrdenTrabajo $ot): void
    {
        abort_unless($u->can('ot.ver_todas') || ($u->can('ot.ver_propias') && $ot->esDe($u)), 403);
    }

    private function puedeEjecutar(User $u, OrdenTrabajo $ot): bool
    {
        return $u->can('ot.editar') || ($u->can('ot.ejecutar') && $ot->laEjecuta($u));
    }

    /** Puede ejecutar la OT y, si tiene turno, está marcado. */
    private function autorizarEjecucion(User $u, OrdenTrabajo $ot): void
    {
        abort_unless($this->puedeEjecutar($u, $ot), 403, 'Solo el responsable asignado puede actualizar esta orden.');
        $this->asistencia->exigirEntrada($u);
    }
}
