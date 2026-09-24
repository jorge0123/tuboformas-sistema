<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Especialidad;
use App\Models\Maquina;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\User;
use App\Services\OrdenTrabajoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('planes.ver');
        $planes = PlanMantenimiento::with(['maquina', 'responsable', 'especialidad'])
            ->withCount(['ordenes as abiertas' => fn ($q) => $q->whereIn('estado', OrdenTrabajo::ABIERTOS)])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('titulo', 'like', '%'.$request->q.'%')
                ->orWhereHas('maquina', fn ($m) => $m->buscar($request->q))))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activos'))
            ->orderByDesc('activo')->orderBy('proxima_fecha')->paginate(30)->withQueryString();

        return view('planes.index', compact('planes'));
    }

    public function create(Request $request)
    {
        $this->authorize('planes.gestionar');

        return view('planes.form', $this->datos(new PlanMantenimiento([
            'maquina_id' => $request->integer('maquina') ?: null, 'tipo' => 'preventivo', 'prioridad' => 'media',
            'frecuencia_valor' => 1, 'frecuencia_unidad' => 'meses', 'proxima_fecha' => today()->addWeek(),
            'dias_anticipacion' => 3, 'activo' => true,
        ])));
    }

    public function store(Request $request)
    {
        $this->authorize('planes.gestionar');
        $plan = PlanMantenimiento::create($this->validar($request));
        Auditoria::registrar('crear', $plan, $plan->titulo);

        return redirect()->route('planes.index')->with('ok', 'Plan creado. La orden se generará '.$plan->dias_anticipacion.' días antes del '.$plan->proxima_fecha->format('d/m/Y').'.');
    }

    public function edit(PlanMantenimiento $plan)
    {
        $this->authorize('planes.gestionar');

        return view('planes.form', $this->datos($plan));
    }

    public function update(Request $request, PlanMantenimiento $plan)
    {
        $this->authorize('planes.gestionar');
        $plan->update($this->validar($request));
        Auditoria::registrar('editar', $plan, $plan->titulo);

        return redirect()->route('planes.index')->with('ok', 'Plan actualizado.');
    }

    public function destroy(PlanMantenimiento $plan)
    {
        $this->authorize('planes.gestionar');
        Auditoria::registrar('eliminar', $plan, $plan->titulo);
        $plan->delete();

        return redirect()->route('planes.index')->with('ok', 'Plan eliminado. Las órdenes que ya generó se conservan.');
    }

    /** Genera ahora las OT que tocan (lo mismo que corre el cron cada mañana). */
    public function generar(Request $request, OrdenTrabajoService $ots)
    {
        $this->authorize('planes.gestionar');
        $n = $ots->generarPreventivas($request->user());

        return back()->with('ok', $n ? "Se generaron $n órdenes preventivas." : 'No hay preventivos pendientes de generar hoy.');
    }

    private function datos(PlanMantenimiento $plan): array
    {
        return [
            'plan' => $plan,
            'maquinas' => Maquina::where('estado', '!=', 'baja')->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'especialidades' => Especialidad::where('activo', true)->orderBy('nombre')->get(),
            'tecnicos' => User::asignables()->get(),
        ];
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'maquina_id' => ['nullable', 'exists:maquinas,id'],
            'titulo' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(['preventivo', 'predictivo'])],
            'especialidad_id' => ['nullable', 'exists:especialidades,id'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'prioridad' => ['required', Rule::in(array_keys(OrdenTrabajo::PRIORIDADES))],
            'frecuencia_valor' => ['required', 'integer', 'min:1', 'max:365'],
            'frecuencia_unidad' => ['required', Rule::in(array_keys(PlanMantenimiento::UNIDADES))],
            'proxima_fecha' => ['required', 'date'],
            'dias_anticipacion' => ['required', 'integer', 'min:0', 'max:60'],
            'duracion_estimada' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ], [], ['titulo' => 'título', 'proxima_fecha' => 'próxima fecha']);
        $d['checklist'] = array_values(array_filter(array_map(fn ($t) => trim((string) $t), $d['checklist'] ?? []))) ?: null;
        $d['activo'] = $request->boolean('activo');

        return $d;
    }
}
