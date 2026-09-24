<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\CategoriaProducto;
use App\Models\Maquina;
use App\Models\Movimiento;
use App\Models\MovimientoLinea;
use App\Models\OrdenTrabajo;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function mantenimiento(Request $request)
    {
        $this->authorize('reportes.mantenimiento');
        [$desde, $hasta] = $this->periodo($request);
        $costos = $request->user()->can('inventario.ver_costos');

        $completadas = OrdenTrabajo::where('estado', 'completada')->whereBetween('completada_at', [$desde, $hasta->copy()->endOfDay()])->get();
        $aTiempo = $completadas->filter(fn ($o) => $o->situacion() === 'completada_a_tiempo')->count();

        $k = [
            'creadas' => OrdenTrabajo::whereBetween('created_at', [$desde, $hasta->copy()->endOfDay()])->count(),
            'completadas' => $completadas->count(),
            'cumplimiento' => $completadas->count() ? round($aTiempo / $completadas->count() * 100) : null,
            'abiertas' => OrdenTrabajo::whereIn('estado', OrdenTrabajo::ABIERTOS)->count(),
            'atrasadas' => OrdenTrabajo::enSituacion('atrasada')->count(),
            'horas' => (float) Bitacora::whereBetween('fecha', [$desde, $hasta])->sum('horas'),
            'paro' => (float) $completadas->where('detuvo_maquina', true)->sum('horas_paro'),
            'mttr' => ($c = $completadas->where('tipo', 'correctivo')->where('detuvo_maquina', true))->count() ? round($c->avg('horas_paro'), 1) : null,
        ];

        $porTipo = Bitacora::whereBetween('fecha', [$desde, $hasta])->selectRaw('tipo, count(*) total')->groupBy('tipo')->pluck('total', 'tipo');

        $porTecnico = Bitacora::whereBetween('fecha', [$desde, $hasta])->whereNotNull('responsable_id')
            ->selectRaw('responsable_id, count(*) trabajos, sum(horas) horas')->groupBy('responsable_id')
            ->orderByDesc('horas')->get()->map(fn ($r) => ['nombre' => User::find($r->responsable_id)?->name, 'trabajos' => $r->trabajos, 'horas' => (float) $r->horas]);

        $porMaquina = Bitacora::whereBetween('fecha', [$desde, $hasta])
            ->selectRaw("maquina_id, sum(tipo = 'correctivo') correctivos, sum(tipo = 'preventivo') preventivos, count(*) total, sum(coalesce(costo,0)) costo_externo")
            ->groupBy('maquina_id')->orderByDesc('correctivos')->limit(10)->get();
        $repuestos = $costos ? MovimientoLinea::join('movimientos', 'movimientos.id', '=', 'movimiento_lineas.movimiento_id')
            ->where('movimientos.tipo', 'consumo_mantenimiento')->where('movimientos.estado', 'confirmado')
            ->whereBetween('movimientos.fecha', [$desde, $hasta])->whereNotNull('movimientos.maquina_id')
            ->selectRaw('movimientos.maquina_id, sum(cantidad_base * coalesce(costo_unitario,0)) total')->groupBy('movimientos.maquina_id')->pluck('total', 'maquina_id') : collect();
        $maquinas = Maquina::whereIn('id', $porMaquina->pluck('maquina_id'))->get()->keyBy('id');

        // Tendencia semanal: creadas vs completadas.
        $semanas = collect();
        for ($s = $desde->copy()->startOfWeek(); $s->lte($hasta); $s->addWeek()) {
            $fin = $s->copy()->endOfWeek();
            $semanas->push([
                'etiqueta' => $s->format('d/m'),
                'creadas' => OrdenTrabajo::whereBetween('created_at', [$s, $fin])->count(),
                'completadas' => OrdenTrabajo::where('estado', 'completada')->whereBetween('completada_at', [$s, $fin])->count(),
            ]);
        }

        return view('reportes.mantenimiento', compact('desde', 'hasta', 'k', 'porTipo', 'porTecnico', 'porMaquina', 'maquinas', 'repuestos', 'semanas', 'costos'));
    }

    public function bodega(Request $request)
    {
        $this->authorize('reportes.bodega');
        [$desde, $hasta] = $this->periodo($request);
        $costos = $request->user()->can('inventario.ver_costos');

        $movs = Movimiento::whereBetween('fecha', [$desde, $hasta])->where('estado', 'confirmado')->where('tipo', '!=', 'reverso');
        $porTipo = (clone $movs)->selectRaw('tipo, count(*) total')->groupBy('tipo')->pluck('total', 'tipo');

        $lineas = fn (array $tipos) => MovimientoLinea::join('movimientos', 'movimientos.id', '=', 'movimiento_lineas.movimiento_id')
            ->whereIn('movimientos.tipo', $tipos)->where('movimientos.estado', 'confirmado')
            ->whereBetween('movimientos.fecha', [$desde, $hasta]);

        $producido = $lineas(['ingreso_produccion'])->selectRaw('producto_id, sum(cantidad_base) total')->groupBy('producto_id')->orderByDesc('total')->limit(10)->get();
        $despachado = $lineas(['salida_despacho'])->selectRaw('producto_id, sum(cantidad_base) total')->groupBy('producto_id')->orderByDesc('total')->limit(10)->get();
        $consumido = $lineas(['salida_produccion'])->selectRaw('producto_id, sum(cantidad_base) total')->groupBy('producto_id')->orderByDesc('total')->limit(10)->get();
        $productos = Producto::with('unidad')->whereIn('id', $producido->pluck('producto_id')->merge($despachado->pluck('producto_id'))->merge($consumido->pluck('producto_id')))->get()->keyBy('id');

        $valor = null;
        $porCategoria = collect();
        $valorMovido = null;
        if ($costos) {
            $inventario = Producto::where('activo', true)->withSum('existencias', 'cantidad')->get();
            $valor = $inventario->sum(fn ($p) => (float) $p->existencias_sum_cantidad * (float) $p->costo_promedio);
            $cats = CategoriaProducto::pluck('nombre', 'id');
            $porCategoria = $inventario->groupBy('categoria_id')->map(fn ($g, $c) => [
                'nombre' => $cats[$c] ?? 'Sin categoría',
                'valor' => $g->sum(fn ($p) => (float) $p->existencias_sum_cantidad * (float) $p->costo_promedio),
            ])->sortByDesc('valor')->values();
            $valorMovido = [
                'compras' => (float) $lineas(['entrada_compra'])->sum(DB::raw('cantidad_base * coalesce(costo_unitario,0)')),
                'produccion' => (float) $lineas(['salida_produccion'])->sum(DB::raw('cantidad_base * coalesce(costo_unitario,0)')),
                'mantenimiento' => (float) $lineas(['consumo_mantenimiento'])->sum(DB::raw('cantidad_base * coalesce(costo_unitario,0)')),
                'ajustes' => (float) $lineas(['ajuste_salida'])->sum(DB::raw('cantidad_base * coalesce(costo_unitario,0)')),
            ];
        }
        $bajoMinimo = Producto::with('unidad')->where('activo', true)->where('stock_minimo', '>', 0)->withSum('existencias', 'cantidad')->get()->filter->bajoMinimo();

        return view('reportes.bodega', compact('desde', 'hasta', 'porTipo', 'producido', 'despachado', 'consumido', 'productos', 'valor', 'porCategoria', 'valorMovido', 'bajoMinimo', 'costos'));
    }

    private function periodo(Request $request): array
    {
        $desde = $request->filled('desde') ? Carbon::parse($request->desde)->startOfDay() : today()->subDays(89);
        $hasta = $request->filled('hasta') ? Carbon::parse($request->hasta)->startOfDay() : today();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }
}
