<?php

namespace App\Http\Controllers;

use App\Models\Maquina;
use App\Models\Movimiento;
use App\Models\OrdenTrabajo;
use App\Models\OtTramo;
use App\Models\PlanMantenimiento;
use App\Models\Producto;
use App\Services\AsistenciaService;
use App\Support\Ocupacion;
use Illuminate\Http\Request;

/**
 * Inicio centrado en mantenimiento: mi jornada (quien marca asistencia), cómo están las órdenes,
 * qué requiere atención, el equipo de hoy, preventivos y máquinas. La bodega solo avisa lo urgente.
 */
class DashboardController extends Controller
{
    public function index(Request $request, AsistenciaService $asistencia)
    {
        $u = $request->user();
        $d = [];

        if ($u->marcaAsistencia()) {
            $abierta = $u->asistenciaAbierta();
            $d['jornada'] = [
                'asistencia' => $abierta,
                'tramo' => $asistencia->tramoAbierto($u),
                'en_ot_hoy' => OtTramo::where('user_id', $u->id)->where(fn ($q) => $q->where('inicio_at', '>=', today())->orWhereNull('fin_at'))->get()->sum(fn ($t) => $t->horas()),
            ];
        }

        if ($u->canAny(['ot.ver_todas', 'ot.ver_propias'])) {
            $base = fn () => OrdenTrabajo::visiblesPara($u);
            $d['ot'] = [
                'abiertas' => $base()->whereIn('estado', OrdenTrabajo::ABIERTOS)->count(),
                'atrasadas' => $base()->enSituacion('atrasada')->count(),
                'por_vencer' => $base()->enSituacion('por_vencer')->count(),
                'en_espera' => $base()->where('estado', 'en_espera')->count(),
                'completadas_mes' => $base()->where('estado', 'completada')->where('completada_at', '>=', now()->startOfMonth())->count(),
            ];
            $d['por_estado'] = $base()->whereIn('estado', OrdenTrabajo::ABIERTOS)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
            $d['mis_ot'] = OrdenTrabajo::with(['maquina', 'responsable'])
                ->whereIn('estado', OrdenTrabajo::ABIERTOS)
                ->where(fn ($q) => $q->where('responsable_id', $u->id)->orWhereHas('ayudantes', fn ($a) => $a->where('users.id', $u->id)))
                ->orderByRaw("FIELD(estado, 'en_progreso', 'pendiente', 'en_espera')")
                ->orderByRaw('fecha_vencimiento is null, fecha_vencimiento')->limit(8)->get();
            if ($u->can('ot.ver_todas')) {
                $d['urgentes'] = OrdenTrabajo::with(['maquina', 'responsable'])
                    ->whereIn('estado', OrdenTrabajo::ABIERTOS)
                    ->where(fn ($q) => $q->whereDate('fecha_vencimiento', '<=', today()->addDays(2))->orWhere('prioridad', 'critica'))
                    ->orderByRaw("prioridad = 'critica' desc")->orderBy('fecha_vencimiento')->limit(8)->get();
                $d['sin_asignar'] = OrdenTrabajo::whereIn('estado', OrdenTrabajo::ABIERTOS)->whereNull('responsable_id')->count();
                // ¿Vamos al día? Últimas 8 semanas.
                $d['semanas'] = collect(range(7, 0))->map(function ($i) {
                    $s = now()->startOfWeek()->subWeeks($i);
                    $f = $s->copy()->endOfWeek();

                    return [
                        'etiqueta' => $s->format('d/m'), 'titulo' => 'Semana del '.$s->format('d/m'),
                        'creadas' => OrdenTrabajo::whereBetween('created_at', [$s, $f])->count(),
                        'completadas' => OrdenTrabajo::where('estado', 'completada')->whereBetween('completada_at', [$s, $f])->count(),
                    ];
                })->all();
            }
        }

        if ($u->can('asistencia.ver')) {
            $d['equipo'] = Ocupacion::hoy();
            $semana = Ocupacion::porPersona(today()->subDays(6), today())->where('marcadas', '>', 0);
            $d['ocupacion_semana'] = $semana->sum('marcadas') > 0 ? (int) round($semana->sum('en_ot') / $semana->sum('marcadas') * 100) : null;
        }

        if ($u->can('maquinas.ver')) {
            $d['maquinas'] = Maquina::where('estado', '!=', 'baja')->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        }

        if ($u->can('planes.ver')) {
            $d['preventivos'] = PlanMantenimiento::with(['maquina', 'responsable'])->where('activo', true)
                ->whereBetween('proxima_fecha', [today(), today()->addDays(14)])->orderBy('proxima_fecha')->limit(6)->get();
        }

        // Bodega de repuestos: solo lo que pide acción.
        if ($u->can('inventario.ver')) {
            $d['bajo_minimo'] = Producto::with('unidad')->where('activo', true)->bajoMinimo()->orderBy('nombre')->limit(5)->get();
            $d['bajo_minimo_total'] = Producto::where('activo', true)->bajoMinimo()->count();
        }
        if ($u->can('movimientos.aprobar')) {
            $d['ajustes_pendientes'] = Movimiento::where('estado', 'pendiente')->count();
        }

        return view('dashboard', compact('d'));
    }
}
