<?php

namespace App\Support;

use App\Models\EnvioProgramado;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\Producto;

/**
 * Arma el contenido de un reporte programado: cada sección elegida con los datos del período
 * (hoy, ayer o 7 días). La agenda siempre mira hacia adelante (lo que toca hoy).
 */
class ReporteProgramado
{
    /** clave => [título, descripción] */
    public const REPORTES = [
        'resumen' => ['Resumen de mantenimiento', 'Órdenes creadas y completadas, a tiempo, horas y paro; cómo quedan las abiertas.'],
        'agenda' => ['Agenda de hoy', 'Órdenes que vencen hoy y preventivos programados para hoy.'],
        'atrasadas' => ['Órdenes atrasadas', 'Lista de órdenes vencidas con su responsable y días de atraso.'],
        'completadas' => ['Trabajo completado', 'Órdenes completadas en el período: qué, quién y cuánto tiempo.'],
        'asistencia' => ['Asistencia y ocupación', 'Horas marcadas, horas en órdenes, ocupación, llegadas tarde y faltas por técnico.'],
        'repuestos' => ['Repuestos por pedir', 'Repuestos e insumos bajo el mínimo.'],
    ];

    public static function armar(EnvioProgramado $envio): array
    {
        [$desde, $hasta, $texto] = $envio->rango();
        $secciones = [];
        foreach (array_keys(self::REPORTES) as $clave) {
            if (in_array($clave, $envio->reportes ?? [], true)) {
                $secciones[$clave] = self::$clave($desde, $hasta);
            }
        }

        return ['envio' => $envio, 'periodo' => $texto, 'secciones' => $secciones, 'generado' => now()];
    }

    private static function resumen($desde, $hasta): array
    {
        $completadas = OrdenTrabajo::where('estado', 'completada')->whereBetween('completada_at', [$desde, $hasta])->get();
        $aTiempo = $completadas->filter(fn ($o) => $o->situacion() === 'completada_a_tiempo')->count();

        return [
            'creadas' => OrdenTrabajo::whereBetween('created_at', [$desde, $hasta])->count(),
            'completadas' => $completadas->count(),
            'a_tiempo' => $completadas->count() ? (int) round($aTiempo / $completadas->count() * 100) : null,
            'horas' => (float) $completadas->sum('horas_trabajo'),
            'paro' => (float) $completadas->where('detuvo_maquina', true)->sum('horas_paro'),
            'abiertas' => OrdenTrabajo::whereIn('estado', OrdenTrabajo::ABIERTOS)->count(),
            'atrasadas' => OrdenTrabajo::enSituacion('atrasada')->count(),
            'en_espera' => OrdenTrabajo::where('estado', 'en_espera')->count(),
            'sin_asignar' => OrdenTrabajo::whereIn('estado', OrdenTrabajo::ABIERTOS)->whereNull('responsable_id')->count(),
        ];
    }

    private static function agenda($desde, $hasta): array
    {
        return [
            'vencen' => OrdenTrabajo::with(['maquina', 'responsable'])->whereIn('estado', OrdenTrabajo::ABIERTOS)
                ->whereDate('fecha_vencimiento', today())->orderByRaw("prioridad = 'critica' desc")->limit(20)->get(),
            'preventivos' => PlanMantenimiento::with(['maquina', 'responsable'])->where('activo', true)
                ->whereDate('proxima_fecha', today())->limit(20)->get(),
            'en_progreso' => OrdenTrabajo::where('estado', 'en_progreso')->count(),
        ];
    }

    private static function atrasadas($desde, $hasta): array
    {
        return [
            'ordenes' => OrdenTrabajo::with(['maquina', 'responsable'])->enSituacion('atrasada')
                ->orderBy('fecha_vencimiento')->limit(20)->get(),
            'total' => OrdenTrabajo::enSituacion('atrasada')->count(),
        ];
    }

    private static function completadas($desde, $hasta): array
    {
        return [
            'ordenes' => OrdenTrabajo::with(['maquina', 'responsable'])->where('estado', 'completada')
                ->whereBetween('completada_at', [$desde, $hasta])->orderByDesc('completada_at')->limit(25)->get(),
        ];
    }

    private static function asistencia($desde, $hasta): array
    {
        $personas = Ocupacion::porPersona($desde->copy()->startOfDay(), $hasta->copy()->startOfDay())->values();
        $marcadas = $personas->sum('marcadas');

        return [
            'personas' => $personas,
            'ocupacion' => $marcadas > 0 ? (int) round($personas->sum('en_ot') / $marcadas * 100) : null,
        ];
    }

    private static function repuestos($desde, $hasta): array
    {
        return ['productos' => Producto::with('unidad')->where('activo', true)->bajoMinimo()->orderBy('nombre')->limit(30)->get()];
    }
}
