<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Bitacora;
use App\Models\Maquina;
use App\Models\OrdenTrabajo;
use App\Models\PlanMantenimiento;
use App\Models\User;
use App\Notifications\Aviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida de una OT (docs/DISENO.md §2.2):
 * crear → asignar → el técnico registra seguimiento (avance, estado, horas) → completar,
 * que crea el registro en la bitácora de la máquina.
 */
class OrdenTrabajoService
{
    public function __construct(private AsistenciaService $asistencia) {}

    public function crear(array $datos, User $usuario): OrdenTrabajo
    {
        $ot = DB::transaction(function () use ($datos, $usuario) {
            $ayudantes = $datos['ayudantes'] ?? [];
            unset($datos['ayudantes']);

            $ot = OrdenTrabajo::create($datos + [
                'folio' => Folios::siguiente('OT'),
                'estado' => 'pendiente',
                'solicitante_id' => $usuario->id,
            ]);
            $ot->ayudantes()->sync($ayudantes);
            $ot->seguimientos()->create([
                'user_id' => $usuario->id,
                'tipo' => 'sistema',
                'texto' => 'Orden creada'.($ot->plan_id ? ' desde el plan preventivo' : '').'.',
                'estado' => 'pendiente',
                'progreso' => 0,
            ]);

            return $ot;
        });

        Auditoria::registrar('crear', $ot, "{$ot->folio} · {$ot->titulo}");
        $this->avisarAsignacion($ot, $usuario);

        // Reporte sin responsable (ej. falla reportada por producción) o crítica: avisar a quien asigna.
        if (! $ot->responsable_id || $ot->prioridad === 'critica') {
            $coordinadores = User::activos()->permission('ot.asignar')->where('id', '!=', $usuario->id)
                ->where('id', '!=', $ot->responsable_id ?? 0)->get();
            Notification::send($coordinadores, new Aviso(
                $ot->responsable_id ? 'Orden crítica' : 'Orden por asignar',
                "{$ot->folio} · {$ot->titulo}".($ot->maquina ? " ({$ot->maquina->etiqueta()})" : '')." — reportada por {$usuario->name}",
                route('ot.show', $ot)
            ));
        }

        return $ot;
    }

    public function actualizar(OrdenTrabajo $ot, array $datos, User $usuario): void
    {
        $responsableAntes = $ot->responsable_id;
        DB::transaction(function () use ($ot, $datos, $usuario, $responsableAntes) {
            $ayudantes = $datos['ayudantes'] ?? null;
            unset($datos['ayudantes']);
            $ot->update($datos);
            // El responsable cargado en memoria es el anterior: se vuelve a leer para avisar al nuevo.
            $ot->unsetRelation('responsable');
            if ($ayudantes !== null) {
                $ot->ayudantes()->sync($ayudantes);
            }
            if ((int) $ot->responsable_id !== (int) $responsableAntes) {
                $ot->seguimientos()->create([
                    'user_id' => $usuario->id,
                    'tipo' => 'asignacion',
                    'texto' => 'Asignada a '.($ot->responsable?->name ?? 'nadie').'.',
                ]);
            }
        });
        Auditoria::registrar('editar', $ot, $ot->folio);
        // El formulario manda el id como texto: se compara como número para no avisar sin cambio real.
        if ((int) $ot->responsable_id !== (int) $responsableAntes) {
            $this->avisarAsignacion($ot, $usuario);
        }
    }

    /**
     * El técnico registra avance: nota, % de avance, estado y horas trabajadas.
     * Las horas se acumulan en la OT. Quien marca asistencia no captura horas: su tiempo
     * corre solo mientras la OT está en progreso (AsistenciaService).
     */
    public function registrarSeguimiento(OrdenTrabajo $ot, array $d, User $usuario): void
    {
        if (! $ot->estaAbierta()) {
            throw ValidationException::withMessages(['estado' => 'La orden ya está cerrada.']);
        }
        $estado = $d['estado'] ?? $ot->estado;
        if ($estado === 'completada' || $estado === 'cancelada') {
            throw ValidationException::withMessages(['estado' => 'Para completar o cancelar usa el botón correspondiente.']);
        }
        if ($estado === 'en_espera' && empty($d['motivo_espera']) && empty($ot->motivo_espera)) {
            throw ValidationException::withMessages(['motivo_espera' => 'Indica qué se está esperando (repuesto, cotización…).']);
        }
        $progreso = isset($d['progreso']) ? (int) $d['progreso'] : $ot->progreso;
        $horas = ! $usuario->marcaAsistencia() && isset($d['horas']) && $d['horas'] !== '' ? (float) $d['horas'] : null;

        DB::transaction(function () use ($ot, $d, $usuario, $estado, $progreso, $horas) {
            $cambioEstado = $estado !== $ot->estado;
            $ot->update([
                'estado' => $estado,
                'progreso' => min(99, max(0, $progreso)), // 100 % solo al completar
                'horas_trabajo' => $horas ? (float) $ot->horas_trabajo + $horas : $ot->horas_trabajo,
                'motivo_espera' => $estado === 'en_espera' ? ($d['motivo_espera'] ?? $ot->motivo_espera) : null,
                'fecha_inicio' => $ot->fecha_inicio ?? ($estado === 'en_progreso' ? today() : null),
            ]);
            $ot->seguimientos()->create([
                'user_id' => $usuario->id,
                'tipo' => $cambioEstado ? 'estado' : 'avance',
                'texto' => trim(($d['texto'] ?? '').($estado === 'en_espera' && $cambioEstado ? "\nEn espera: ".$ot->motivo_espera : '')) ?: null,
                'progreso' => $ot->progreso,
                'estado' => $estado,
                'horas' => $horas,
            ]);
            if ($estado === 'en_progreso') {
                $this->asistencia->iniciarTrabajo($ot, $usuario);
            } else {
                $this->asistencia->detenerOrden($ot, $estado === 'en_espera' ? 'espera' : 'pausa');
            }
            $this->marcarMaquina($ot);
        });

        $this->avisarInteresados($ot, $usuario, 'Avance en '.$ot->folio, "{$usuario->name}: {$ot->progreso}% · ".OrdenTrabajo::ESTADOS[$ot->estado]);

        // Una OT detenida casi siempre necesita a quien coordina (comprar un repuesto, pedir cotización…).
        if ($ot->estado === 'en_espera' && $ot->wasChanged('estado')) {
            $ya = collect([$ot->responsable_id, $ot->solicitante_id, $usuario->id])->merge($ot->ayudantes->pluck('id'));
            Notification::send(
                User::activos()->permission('ot.asignar')->whereNotIn('id', $ya->filter())->get(),
                new Aviso("{$ot->folio} en espera", "{$ot->titulo}: {$ot->motivo_espera}", route('ot.show', $ot))
            );
        }
    }

    public function comentar(OrdenTrabajo $ot, string $texto, User $usuario): void
    {
        $ot->seguimientos()->create(['user_id' => $usuario->id, 'tipo' => 'comentario', 'texto' => $texto]);
        $this->avisarInteresados($ot, $usuario, 'Comentario en '.$ot->folio, "{$usuario->name}: ".mb_strimwidth($texto, 0, 120, '…'));
    }

    /** Completa la OT y la manda a la bitácora de la máquina. */
    public function completar(OrdenTrabajo $ot, array $d, User $usuario): void
    {
        if (! $ot->estaAbierta()) {
            throw ValidationException::withMessages(['estado' => 'La orden ya está cerrada.']);
        }

        DB::transaction(function () use ($ot, $d, $usuario) {
            // El tiempo que venía corriendo se suma antes de pasar las horas a la bitácora.
            $this->asistencia->detenerOrden($ot, 'completada');
            $ot->refresh();
            $horas = ! $usuario->marcaAsistencia() && isset($d['horas']) && $d['horas'] !== '' ? (float) $d['horas'] : null;
            $ot->update([
                'estado' => 'completada',
                'progreso' => 100,
                'completada_at' => now(),
                'trabajo_realizado' => $d['trabajo_realizado'],
                'horas_trabajo' => $horas ? (float) $ot->horas_trabajo + $horas : $ot->horas_trabajo,
                'detuvo_maquina' => ! empty($d['detuvo_maquina']),
                'horas_paro' => ! empty($d['detuvo_maquina']) ? ($d['horas_paro'] ?? null) : null,
                'motivo_espera' => null,
            ]);
            $ot->seguimientos()->create([
                'user_id' => $usuario->id,
                'tipo' => 'estado',
                'texto' => "Completada.\n".$d['trabajo_realizado'],
                'progreso' => 100,
                'estado' => 'completada',
                'horas' => $horas,
            ]);

            if ($ot->maquina_id) {
                Bitacora::create([
                    'maquina_id' => $ot->maquina_id,
                    'orden_trabajo_id' => $ot->id,
                    'fecha' => today(),
                    'tipo' => $ot->tipo,
                    'componente' => $d['componente'] ?? null,
                    'trabajo_realizado' => $d['trabajo_realizado'],
                    'horas' => $ot->horas_trabajo,
                    'responsable_id' => $ot->responsable_id ?? $usuario->id,
                    'proveedor_id' => $d['proveedor_id'] ?? null,
                    'garantia' => ! empty($d['garantia']),
                    'costo' => $d['costo'] ?? null,
                    'horometro' => $d['horometro'] ?? null,
                    'comentarios' => $d['comentarios'] ?? null,
                    'user_id' => $usuario->id,
                ]);
                if (! empty($d['horometro'])) {
                    $ot->maquina->update(['horometro' => $d['horometro']]);
                }
            }
            $this->marcarMaquina($ot);
        });

        Auditoria::registrar('completar', $ot, $ot->folio);
        $this->avisarInteresados($ot, $usuario, "{$ot->folio} completada", $ot->titulo);
    }

    public function cancelar(OrdenTrabajo $ot, string $motivo, User $usuario): void
    {
        if (! $ot->estaAbierta()) {
            throw ValidationException::withMessages(['estado' => 'La orden ya está cerrada.']);
        }
        DB::transaction(function () use ($ot, $motivo, $usuario) {
            $this->asistencia->detenerOrden($ot, 'cancelada');
            $ot->update(['estado' => 'cancelada', 'motivo_espera' => null]);
            $ot->seguimientos()->create([
                'user_id' => $usuario->id, 'tipo' => 'estado', 'texto' => "Cancelada: $motivo", 'estado' => 'cancelada',
            ]);
            $this->marcarMaquina($ot);
        });
        Auditoria::registrar('cancelar', $ot, "{$ot->folio}: $motivo");
        $this->avisarInteresados($ot, $usuario, "{$ot->folio} cancelada", "{$ot->titulo} — $motivo");
    }

    /** Movimiento en el Kanban (arrastrar tarjeta). Completar/cancelar tienen su propio flujo. */
    public function moverKanban(OrdenTrabajo $ot, string $estado, User $usuario): void
    {
        $this->registrarSeguimiento($ot, ['estado' => $estado, 'texto' => null], $usuario);
    }

    /** Genera las OT de los planes que ya entraron en su ventana de anticipación. */
    public function generarPreventivas(?User $sistema = null): int
    {
        $sistema ??= User::role('super_admin')->orderBy('id')->first();
        $creadas = 0;

        $planes = PlanMantenimiento::where('activo', true)
            ->whereRaw('DATE_SUB(proxima_fecha, INTERVAL dias_anticipacion DAY) <= ?', [today()->toDateString()])
            ->get();

        foreach ($planes as $plan) {
            // Nunca dos OT abiertas del mismo plan.
            if ($plan->ordenes()->whereIn('estado', OrdenTrabajo::ABIERTOS)->exists()) {
                continue;
            }
            $this->crear([
                'titulo' => $plan->titulo,
                'descripcion' => $plan->descripcion,
                'maquina_id' => $plan->maquina_id,
                'plan_id' => $plan->id,
                'tipo' => $plan->tipo,
                'especialidad_id' => $plan->especialidad_id,
                'prioridad' => $plan->prioridad,
                'responsable_id' => $plan->responsable_id,
                'fecha_vencimiento' => $plan->proxima_fecha,
                'checklist' => collect($plan->checklist ?? [])->map(fn ($t) => ['texto' => $t, 'hecho' => false])->all(),
            ], $sistema);
            $plan->update(['proxima_fecha' => $plan->siguienteFecha($plan->proxima_fecha)]);
            $creadas++;
        }

        return $creadas;
    }

    /** La máquina pasa a "en mantenimiento" mientras tenga OT en progreso que la detienen. */
    private function marcarMaquina(OrdenTrabajo $ot): void
    {
        $maquina = $ot->maquina_id ? Maquina::find($ot->maquina_id) : null;
        if (! $maquina || in_array($maquina->estado, ['baja', 'fuera_servicio'])) {
            return;
        }
        $enCurso = OrdenTrabajo::where('maquina_id', $maquina->id)->where('estado', 'en_progreso')
            ->whereIn('tipo', ['correctivo', 'preventivo'])->exists();
        $maquina->update(['estado' => $enCurso ? 'en_mantenimiento' : 'operativa']);
    }

    private function avisarAsignacion(OrdenTrabajo $ot, User $autor): void
    {
        if ($ot->responsable_id && (int) $ot->responsable_id !== (int) $autor->id) {
            $ot->responsable->notify(new Aviso(
                'Nueva orden asignada',
                "{$ot->folio} · {$ot->titulo}".($ot->fecha_vencimiento ? ' · vence '.$ot->fecha_vencimiento->format('d/m/Y') : ''),
                route('ot.show', $ot)
            ));
        }
    }

    private function avisarInteresados(OrdenTrabajo $ot, User $autor, string $titulo, string $mensaje): void
    {
        $ot->loadMissing('ayudantes');
        $ids = collect([$ot->responsable_id, $ot->solicitante_id])->merge($ot->ayudantes->pluck('id'))
            ->filter()->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => $id === (int) $autor->id);
        foreach (User::whereIn('id', $ids)->activos()->get() as $u) {
            $u->notify(new Aviso($titulo, $mensaje, route('ot.show', $ot)));
        }
    }
}
