<?php

namespace App\Http\Controllers;

use App\Models\Maquina;
use App\Models\Movimiento;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\Producto;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $d = [];

        if ($u->canAny(['ot.ver_todas', 'ot.ver_propias'])) {
            $base = fn () => OrdenTrabajo::visiblesPara($u);
            $d['ot'] = [
                'abiertas' => $base()->whereIn('estado', OrdenTrabajo::ABIERTOS)->count(),
                'atrasadas' => $base()->enSituacion('atrasada')->count(),
                'por_vencer' => $base()->enSituacion('por_vencer')->count(),
                'en_espera' => $base()->where('estado', 'en_espera')->count(),
                'completadas_mes' => $base()->where('estado', 'completada')->where('completada_at', '>=', now()->startOfMonth())->count(),
            ];
            $d['por_estado'] = $base()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
            $d['mis_ot'] = OrdenTrabajo::with(['maquina', 'responsable'])
                ->whereIn('estado', OrdenTrabajo::ABIERTOS)
                ->where(fn ($q) => $q->where('responsable_id', $u->id)->orWhereHas('ayudantes', fn ($a) => $a->where('users.id', $u->id)))
                ->orderByRaw('fecha_vencimiento is null, fecha_vencimiento')->limit(8)->get();
            if ($u->can('ot.ver_todas')) {
                $d['urgentes'] = OrdenTrabajo::with(['maquina', 'responsable'])
                    ->whereIn('estado', OrdenTrabajo::ABIERTOS)
                    ->where(fn ($q) => $q->whereDate('fecha_vencimiento', '<=', today()->addDays(2))->orWhere('prioridad', 'critica'))
                    ->orderBy('fecha_vencimiento')->limit(8)->get();
                $d['por_tecnico'] = OrdenTrabajo::whereIn('estado', OrdenTrabajo::ABIERTOS)->whereNotNull('responsable_id')
                    ->selectRaw('responsable_id, count(*) as total')->groupBy('responsable_id')
                    ->with('responsable:id,name')->orderByDesc('total')->limit(6)->get();
            }
        }

        if ($u->can('maquinas.ver')) {
            $d['maquinas'] = Maquina::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        }

        if ($u->can('planes.ver')) {
            $d['preventivos'] = PlanMantenimiento::with(['maquina', 'responsable'])->where('activo', true)
                ->whereBetween('proxima_fecha', [today(), today()->addDays(14)])->orderBy('proxima_fecha')->limit(6)->get();
        }

        if ($u->can('inventario.ver')) {
            $d['bajo_minimo'] = Producto::with('unidad')->where('activo', true)->where('stock_minimo', '>', 0)
                ->withSum('existencias', 'cantidad')->get()->filter->bajoMinimo()->take(8);
        }
        if ($u->can('movimientos.aprobar')) {
            $d['pendientes'] = Movimiento::with('user')->where('estado', 'pendiente')->latest()->limit(6)->get();
        }
        if ($u->can('movimientos.ver')) {
            $d['movimientos_hoy'] = Movimiento::whereDate('created_at', today())->where('tipo', '!=', 'reverso')->count();
            $d['ultimos_mov'] = Movimiento::with('user')->where('estado', '!=', 'rechazado')->latest()->limit(5)->get();
        }

        if ($u->canAny(['pedidos.ver', 'pedidos.ver_todos', 'pedidos.preparar'])) {
            $base = fn () => \App\Models\Pedido::visiblesPara($u);
            $d['pedidos'] = [
                'nuevo' => $base()->where('estado', 'nuevo')->count(),
                'preparando' => $base()->where('estado', 'preparando')->count(),
                'listo' => $base()->where('estado', 'listo')->count(),
                'en_ruta' => $base()->where('estado', 'en_ruta')->count(),
                'atrasados' => $base()->activos()->whereDate('fecha_entrega', '<', today())->count(),
            ];
            // Lo que hay que mover hoy: atrasados, de hoy y de mañana; lo urgente primero.
            $d['pedidos_proximos'] = $base()->activos()->with(['cliente', 'preparador'])->withCount(['lineas', 'lineas as armadas' => fn ($q) => $q->where('preparada', true)])
                ->whereDate('fecha_entrega', '<=', today()->addDay())
                ->orderByRaw("case when prioridad = 'urgente' then 0 else 1 end")->orderBy('fecha_entrega')->orderBy('id')->limit(8)->get();
        }

        return view('dashboard', compact('d'));
    }
}
