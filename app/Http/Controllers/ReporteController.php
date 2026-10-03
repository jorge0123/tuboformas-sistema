<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\CategoriaProducto;
use App\Models\Maquina;
use App\Models\Movimiento;
use App\Models\MovimientoLinea;
use App\Models\OrdenTrabajo;
use App\Models\Producto;
use App\Support\Ocupacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function mantenimiento(Request $request)
    {
        $this->authorize('reportes.mantenimiento');
        [$desde, $hasta] = $this->periodo($request);
        $fin = $hasta->copy()->endOfDay();
        $costos = $request->user()->can('inventario.ver_costos');

        $completadas = OrdenTrabajo::with(['responsable', 'especialidad'])->where('estado', 'completada')->whereBetween('completada_at', [$desde, $fin])->get();
        $aTiempo = fn ($c) => $c->filter(fn ($o) => $o->situacion() === 'completada_a_tiempo')->count();
        $correctivasParo = $completadas->where('tipo', 'correctivo')->where('detuvo_maquina', true);
        $preventivas = $completadas->where('tipo', 'preventivo');

        $k = [
            'creadas' => OrdenTrabajo::whereBetween('created_at', [$desde, $fin])->count(),
            'completadas' => $completadas->count(),
            'cumplimiento' => $completadas->count() ? (int) round($aTiempo($completadas) / $completadas->count() * 100) : null,
            'preventivo' => $preventivas->count() ? (int) round($aTiempo($preventivas) / $preventivas->count() * 100) : null,
            'abiertas' => OrdenTrabajo::whereIn('estado', OrdenTrabajo::ABIERTOS)->count(),
            'atrasadas' => OrdenTrabajo::enSituacion('atrasada')->count(),
            'en_espera' => OrdenTrabajo::where('estado', 'en_espera')->count(),
            'horas' => (float) $completadas->sum('horas_trabajo'),
            'paro' => (float) $completadas->where('detuvo_maquina', true)->sum('horas_paro'),
            'mttr' => $correctivasParo->count() ? round($correctivasParo->avg('horas_paro'), 1) : null,
        ];

        // Lo que se hizo, por tipo (OT completadas).
        $porTipo = $completadas->countBy('tipo')->sortKeysUsing(fn ($a, $b) => array_search($a, array_keys(OrdenTrabajo::TIPOS)) <=> array_search($b, array_keys(OrdenTrabajo::TIPOS)));
        $porEspecialidad = $completadas->groupBy(fn ($o) => $o->especialidad?->nombre ?? 'Sin especialidad')->map->count()->sortDesc();
        $abiertasPorEstado = OrdenTrabajo::whereIn('estado', OrdenTrabajo::ABIERTOS)->selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado');

        // Técnicos: lo que completó, a tiempo, horas y ocupación (si marca asistencia).
        $ocupacion = Ocupacion::porPersona($desde, $hasta)->keyBy(fn ($o) => $o->usuario->id);
        $porTecnico = $completadas->whereNotNull('responsable_id')->groupBy('responsable_id')->map(fn ($g) => (object) [
            'usuario' => $g->first()->responsable,
            'completadas' => $g->count(),
            'a_tiempo' => (int) round($aTiempo($g) / $g->count() * 100),
            'horas' => (float) $g->sum('horas_trabajo'),
            'correctivas' => $g->where('tipo', 'correctivo')->count(),
            'ocupacion' => $ocupacion[$g->first()->responsable_id]->ocupacion ?? null,
            'abiertas' => OrdenTrabajo::where('responsable_id', $g->first()->responsable_id)->whereIn('estado', OrdenTrabajo::ABIERTOS)->count(),
        ])->sortByDesc('completadas')->values();

        // Máquinas: dónde se va el mantenimiento.
        $porMaquina = Bitacora::whereBetween('fecha', [$desde, $hasta])
            ->selectRaw("maquina_id, sum(tipo = 'correctivo') correctivos, sum(tipo = 'preventivo') preventivos, count(*) total, sum(coalesce(horas,0)) horas, sum(coalesce(costo,0)) costo_externo")
            ->groupBy('maquina_id')->orderByDesc('correctivos')->orderByDesc('total')->limit(10)->get();
        $paroPorMaquina = $completadas->where('detuvo_maquina', true)->groupBy('maquina_id')->map(fn ($g) => (float) $g->sum('horas_paro'));
        $repuestos = $costos ? MovimientoLinea::join('movimientos', 'movimientos.id', '=', 'movimiento_lineas.movimiento_id')
            ->where('movimientos.tipo', 'consumo_mantenimiento')->where('movimientos.estado', 'confirmado')
            ->whereBetween('movimientos.fecha', [$desde, $hasta])->whereNotNull('movimientos.maquina_id')
            ->selectRaw('movimientos.maquina_id, sum(cantidad * coalesce(costo_unitario,0)) total')->groupBy('movimientos.maquina_id')->pluck('total', 'maquina_id') : collect();
        $maquinas = Maquina::whereIn('id', $porMaquina->pluck('maquina_id'))->get()->keyBy('id');
        $estadoMaquinas = Maquina::where('estado', '!=', 'baja')->selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado');

        // Tendencia semanal: creadas vs completadas.
        $semanas = collect();
        for ($s = $desde->copy()->startOfWeek(); $s->lte($hasta); $s->addWeek()) {
            $finS = $s->copy()->endOfWeek();
            $semanas->push([
                'etiqueta' => $s->format('d/m'),
                'creadas' => OrdenTrabajo::whereBetween('created_at', [$s, $finS])->count(),
                'completadas' => $completadas->filter(fn ($o) => $o->completada_at->between($s, $finS))->count(),
            ]);
        }

        return view('reportes.mantenimiento', compact('desde', 'hasta', 'k', 'porTipo', 'porEspecialidad', 'abiertasPorEstado',
            'porTecnico', 'porMaquina', 'paroPorMaquina', 'maquinas', 'estadoMaquinas', 'repuestos', 'semanas', 'costos'));
    }

    /** Ocupación del personal: horas marcadas contra horas trabajando en órdenes. */
    public function ocupacion(Request $request)
    {
        $this->authorize('asistencia.ver');
        [$desde, $hasta] = $this->periodo($request, 13);
        $personas = Ocupacion::porPersona($desde, $hasta);
        $conMarcas = $personas->where('marcadas', '>', 0);
        $marcadas = $conMarcas->sum('marcadas');
        $enOt = $conMarcas->sum('en_ot');

        $k = [
            'ocupacion' => $marcadas > 0 ? (int) round($enOt / $marcadas * 100) : null,
            'marcadas' => $marcadas,
            'en_ot' => $enOt,
            'tardanzas' => $personas->sum('tardanzas'),
            'faltas' => $personas->sum('faltas'),
            'extra' => $personas->sum('extra'),
            'sin_salida' => $personas->sum('sin_salida'),
        ];

        return view('reportes.ocupacion', [
            'desde' => $desde, 'hasta' => $hasta, 'k' => $k,
            'personas' => $personas->sortByDesc(fn ($p) => $p->ocupacion ?? -1)->values(),
            'dias' => Ocupacion::porDia($desde, $hasta),
        ]);
    }

    public function repuestos(Request $request)
    {
        $this->authorize('reportes.repuestos');
        [$desde, $hasta] = $this->periodo($request);
        $costos = $request->user()->can('inventario.ver_costos');

        $porTipo = Movimiento::whereBetween('fecha', [$desde, $hasta])->where('estado', 'confirmado')->where('tipo', '!=', 'reverso')
            ->selectRaw('tipo, count(*) total')->groupBy('tipo')->pluck('total', 'tipo');

        $lineas = fn (array $tipos) => MovimientoLinea::join('movimientos', 'movimientos.id', '=', 'movimiento_lineas.movimiento_id')
            ->whereIn('movimientos.tipo', $tipos)->where('movimientos.estado', 'confirmado')
            ->whereBetween('movimientos.fecha', [$desde, $hasta]);
        $valorDe = fn (array $tipos) => (float) $lineas($tipos)->sum(DB::raw('cantidad * coalesce(costo_unitario,0)'));

        // Lo más usado en las OT del período, en cantidad y en dinero.
        $consumido = $lineas(['consumo_mantenimiento'])
            ->selectRaw('producto_id, sum(cantidad) total, sum(cantidad * coalesce(costo_unitario,0)) valor')
            ->groupBy('producto_id')->orderByDesc($costos ? 'valor' : 'total')->limit(10)->get();
        $productos = Producto::with('unidad')->whereIn('id', $consumido->pluck('producto_id'))->get()->keyBy('id');
        $porMaquina = $costos ? $lineas(['consumo_mantenimiento'])->whereNotNull('movimientos.maquina_id')
            ->selectRaw('movimientos.maquina_id, sum(cantidad * coalesce(costo_unitario,0)) total')
            ->groupBy('movimientos.maquina_id')->orderByDesc('total')->limit(10)->get() : collect();
        $maquinas = Maquina::whereIn('id', $porMaquina->pluck('maquina_id'))->get()->keyBy('id');

        $valor = null;
        $porCategoria = collect();
        $valorMovido = null;
        if ($costos) {
            $inventario = Producto::where('activo', true)->get();
            $valor = $inventario->sum(fn ($p) => (float) $p->existencia * (float) $p->costo_promedio);
            $cats = CategoriaProducto::pluck('nombre', 'id');
            $porCategoria = $inventario->groupBy('categoria_id')->map(fn ($g, $c) => [
                'nombre' => $cats[$c] ?? 'Sin categoría',
                'valor' => $g->sum(fn ($p) => (float) $p->existencia * (float) $p->costo_promedio),
            ])->sortByDesc('valor')->values();
            $valorMovido = [
                'compras' => $valorDe(['entrada_compra']),
                'mantenimiento' => $valorDe(['consumo_mantenimiento']),
                'ajustes' => $valorDe(['ajuste_salida']),
            ];
        }
        $bajoMinimo = Producto::with('unidad')->where('activo', true)->bajoMinimo()->orderBy('nombre')->get();

        return view('reportes.repuestos', compact('desde', 'hasta', 'porTipo', 'consumido', 'productos', 'porMaquina', 'maquinas',
            'valor', 'porCategoria', 'valorMovido', 'bajoMinimo', 'costos'));
    }

    private function periodo(Request $request, int $diasPorDefecto = 89): array
    {
        $desde = $request->filled('desde') ? Carbon::parse($request->desde)->startOfDay() : today()->subDays($diasPorDefecto);
        $hasta = $request->filled('hasta') ? Carbon::parse($request->hasta)->startOfDay() : today();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }
}
