<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function index(Request $request)
    {
        $this->autorizarAlguno(['planes.ver', 'ot.ver_todas', 'ot.ver_propias']);
        $u = $request->user();

        $mes = rescue(fn () => Carbon::createFromFormat('Y-m', $request->input('mes', now()->format('Y-m')))->startOfMonth(), now()->startOfMonth(), false);
        $inicio = $mes->copy()->startOfWeek(Carbon::MONDAY);
        $fin = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $eventos = collect();

        if ($u->canAny(['ot.ver_todas', 'ot.ver_propias'])) {
            OrdenTrabajo::visiblesPara($u)->with(['maquina', 'responsable'])
                ->whereBetween('fecha_vencimiento', [$inicio->toDateString(), $fin->toDateString()])
                ->where('estado', '!=', 'cancelada')
                ->when($request->filled('responsable'), fn ($q) => $q->where('responsable_id', $request->responsable))
                ->when($request->filled('especialidad'), fn ($q) => $q->where('especialidad_id', $request->especialidad))
                ->get()
                ->each(fn ($ot) => $eventos->push([
                    'fecha' => $ot->fecha_vencimiento->toDateString(),
                    'tipo' => 'ot',
                    'titulo' => $ot->titulo,
                    'detalle' => $ot->folio.' · '.($ot->maquina?->etiqueta() ?? 'General').' · '.($ot->responsable?->name ?? 'Sin asignar'),
                    'url' => route('ot.show', $ot),
                    'estado' => $ot->estado,
                    'situacion' => $ot->situacion(),
                    'plan_id' => $ot->plan_id,
                ]));
        }

        // Próximas repeticiones de los planes dentro del rango (lo que "va a venir").
        if ($u->can('planes.ver')) {
            $planes = PlanMantenimiento::with(['maquina', 'responsable'])->where('activo', true)
                ->where('proxima_fecha', '<=', $fin->toDateString())
                ->when($request->filled('responsable'), fn ($q) => $q->where('responsable_id', $request->responsable))
                ->when($request->filled('especialidad'), fn ($q) => $q->where('especialidad_id', $request->especialidad))
                ->get();
            foreach ($planes as $plan) {
                $fecha = $plan->proxima_fecha->copy();
                for ($i = 0; $i < 60 && $fecha->lte($fin); $i++, $fecha = $plan->siguienteFecha($fecha)) {
                    if ($fecha->gte($inicio)) {
                        $eventos->push([
                            'fecha' => $fecha->toDateString(),
                            'tipo' => 'plan',
                            'titulo' => $plan->titulo,
                            'detalle' => ($plan->maquina?->etiqueta() ?? 'General').' · '.$plan->frecuenciaTexto().' · '.($plan->responsable?->name ?? 'Sin responsable'),
                            'url' => $u->can('planes.gestionar') ? route('planes.edit', $plan) : null,
                            'estado' => 'programado',
                            'situacion' => null,
                            'plan_id' => $plan->id,
                        ]);
                    }
                }
            }
        }

        $porDia = $eventos->sortBy('tipo')->groupBy('fecha');
        $tecnicos = User::asignables()->get();
        $especialidades = Especialidad::where('activo', true)->orderBy('nombre')->get();

        return view('calendario.index', compact('mes', 'inicio', 'fin', 'porDia', 'tecnicos', 'especialidades'));
    }
}
