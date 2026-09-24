<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Existencia;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\User;
use App\Notifications\Aviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Único lugar donde cambia la existencia. Reglas (docs/DISENO.md §2.7):
 * - la existencia nunca queda negativa;
 * - un movimiento confirmado no se edita: se anula con un reverso;
 * - los ajustes quedan pendientes si quien los registra no puede aprobar;
 * - la recepción de compra con costo recalcula el costo promedio ponderado.
 */
class InventarioService
{
    /**
     * @param  array  $datos  tipo, fecha, bodega_origen_id, bodega_destino_id, proveedor_id, maquina_id,
     *                        orden_trabajo_id, documento, referencia, notas
     * @param  array  $lineas  [[producto_id, presentacion_id?, cantidad, costo_unitario?, notas?], …]
     */
    public function registrar(array $datos, array $lineas, User $usuario): Movimiento
    {
        $tipo = Movimiento::TIPOS[$datos['tipo']] ?? null;
        if (! $tipo || ! empty($tipo['interno'])) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de movimiento no válido.']);
        }
        $efecto = $tipo['efecto'];
        $this->validarBodegas($efecto, $datos);
        if (! $lineas) {
            throw ValidationException::withMessages(['lineas' => 'Agrega al menos un producto.']);
        }

        $pendiente = ! empty($tipo['aprobacion']) && ! $usuario->can('movimientos.aprobar');

        $mov = DB::transaction(function () use ($datos, $lineas, $usuario, $efecto, $pendiente) {
            $mov = Movimiento::create([
                'folio' => Folios::siguiente('MOV'),
                'tipo' => $datos['tipo'],
                'efecto' => $efecto,
                'estado' => $pendiente ? 'pendiente' : 'confirmado',
                'fecha' => $datos['fecha'] ?? today(),
                'bodega_origen_id' => in_array($efecto, ['salida', 'traslado']) ? $datos['bodega_origen_id'] : null,
                'bodega_destino_id' => in_array($efecto, ['entrada', 'traslado']) ? $datos['bodega_destino_id'] : null,
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'maquina_id' => $datos['maquina_id'] ?? null,
                'orden_trabajo_id' => $datos['orden_trabajo_id'] ?? null,
                'documento' => $datos['documento'] ?? null,
                'referencia' => $datos['referencia'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'user_id' => $usuario->id,
                'aprobado_por' => $pendiente ? null : $usuario->id,
                'aprobado_at' => $pendiente ? null : now(),
            ]);

            foreach ($lineas as $l) {
                $factor = 1;
                if (! empty($l['presentacion_id'])) {
                    $pres = ProductoPresentacion::where('producto_id', $l['producto_id'])
                        ->findOrFail($l['presentacion_id']);
                    $factor = (float) $pres->factor;
                }
                $cantidad = (float) $l['cantidad'];
                if ($cantidad <= 0) {
                    throw ValidationException::withMessages(['lineas' => 'Las cantidades deben ser mayores a cero.']);
                }
                $costo = isset($l['costo_unitario']) && $l['costo_unitario'] !== ''
                    ? (float) $l['costo_unitario'] / $factor // se captura por presentación, se guarda por unidad base
                    : null;
                $mov->lineas()->create([
                    'producto_id' => $l['producto_id'],
                    'presentacion_id' => ($l['presentacion_id'] ?? null) ?: null,
                    'cantidad' => $cantidad,
                    'factor' => $factor,
                    'cantidad_base' => round($cantidad * $factor, 3),
                    'costo_unitario' => $costo,
                    'notas' => $l['notas'] ?? null,
                ]);
            }

            if (! $pendiente) {
                $this->aplicar($mov);
            }

            return $mov;
        });

        Auditoria::registrar('crear', $mov, "{$mov->folio} · {$mov->nombreTipo()}");
        if ($pendiente) {
            $this->avisarPendiente($mov);
        } else {
            $this->avisarBajoMinimo($mov);
        }

        return $mov;
    }

    public function aprobar(Movimiento $mov, User $usuario): void
    {
        if ($mov->estado !== 'pendiente') {
            throw ValidationException::withMessages(['estado' => 'Solo se aprueban movimientos pendientes.']);
        }
        DB::transaction(function () use ($mov, $usuario) {
            $mov->update(['estado' => 'confirmado', 'aprobado_por' => $usuario->id, 'aprobado_at' => now()]);
            $this->aplicar($mov);
        });
        Auditoria::registrar('aprobar', $mov, $mov->folio);
        $mov->user->notify(new Aviso('Ajuste aprobado', "{$mov->folio} fue aprobado.", route('movimientos.show', $mov)));
        $this->avisarBajoMinimo($mov);
    }

    public function rechazar(Movimiento $mov, User $usuario, string $motivo): void
    {
        if ($mov->estado !== 'pendiente') {
            throw ValidationException::withMessages(['estado' => 'Solo se rechazan movimientos pendientes.']);
        }
        $mov->update(['estado' => 'rechazado', 'aprobado_por' => $usuario->id, 'aprobado_at' => now(), 'motivo_rechazo' => $motivo]);
        Auditoria::registrar('rechazar', $mov, "{$mov->folio}: $motivo");
        $mov->user->notify(new Aviso('Ajuste rechazado', "{$mov->folio}: $motivo", route('movimientos.show', $mov)));
    }

    /** Anula un movimiento confirmado creando su reverso. */
    public function anular(Movimiento $mov, User $usuario, string $motivo): Movimiento
    {
        if ($mov->estado !== 'confirmado' || $mov->tipo === 'reverso') {
            throw ValidationException::withMessages(['estado' => 'Este movimiento no se puede anular.']);
        }

        $reverso = DB::transaction(function () use ($mov, $usuario, $motivo) {
            $efecto = ['entrada' => 'salida', 'salida' => 'entrada', 'traslado' => 'traslado'][$mov->efecto];
            $reverso = Movimiento::create([
                'folio' => Folios::siguiente('MOV'),
                'tipo' => 'reverso',
                'efecto' => $efecto,
                'estado' => 'confirmado',
                'fecha' => today(),
                // El reverso sale de donde entró y entra a donde salió.
                'bodega_origen_id' => $mov->efecto === 'salida' ? null : $mov->bodega_destino_id,
                'bodega_destino_id' => $mov->efecto === 'entrada' ? null : $mov->bodega_origen_id,
                'movimiento_origen_id' => $mov->id,
                'orden_trabajo_id' => $mov->orden_trabajo_id,
                'referencia' => "Anulación de {$mov->folio}",
                'notas' => $motivo,
                'user_id' => $usuario->id,
                'aprobado_por' => $usuario->id,
                'aprobado_at' => now(),
            ]);
            foreach ($mov->lineas as $l) {
                $reverso->lineas()->create($l->only(['producto_id', 'presentacion_id', 'cantidad', 'factor', 'cantidad_base', 'costo_unitario']));
            }
            $this->aplicar($reverso);
            $mov->update(['estado' => 'anulado']);

            return $reverso;
        });

        Auditoria::registrar('anular', $mov, "{$mov->folio}: $motivo");

        return $reverso;
    }

    /** Aplica las líneas a las existencias y guarda el saldo resultante (kárdex). */
    private function aplicar(Movimiento $mov): void
    {
        $mov->load('lineas.producto');
        foreach ($mov->lineas as $linea) {
            $cant = (float) $linea->cantidad_base;
            $cambios = [];

            if (in_array($mov->efecto, ['salida', 'traslado'])) {
                $ex = $this->existencia($linea->producto_id, $mov->bodega_origen_id);
                $nuevo = round((float) $ex->cantidad - $cant, 3);
                if ($nuevo < 0) {
                    throw ValidationException::withMessages([
                        'lineas' => "No hay existencia suficiente de {$linea->producto->etiqueta()}: hay "
                            .rtrim(rtrim(number_format((float) $ex->cantidad, 3, '.', ''), '0'), '.')
                            .', se necesitan '.rtrim(rtrim(number_format($cant, 3, '.', ''), '0'), '.').'.',
                    ]);
                }
                $ex->update(['cantidad' => $nuevo]);
                $cambios['saldo_origen'] = $nuevo;
            }

            if (in_array($mov->efecto, ['entrada', 'traslado'])) {
                $ex = $this->existencia($linea->producto_id, $mov->bodega_destino_id);
                if ($mov->tipo === 'entrada_compra' && $linea->costo_unitario !== null) {
                    $this->recalcularCosto($linea->producto, $cant, (float) $linea->costo_unitario);
                }
                $nuevo = round((float) $ex->cantidad + $cant, 3);
                $ex->update(['cantidad' => $nuevo]);
                $cambios['saldo_destino'] = $nuevo;
            }

            if ($linea->costo_unitario === null) {
                $cambios['costo_unitario'] = $linea->producto->fresh()->costo_promedio;
            }
            $linea->update($cambios);
        }
    }

    private function existencia(int $productoId, int $bodegaId): Existencia
    {
        Existencia::firstOrCreate(['producto_id' => $productoId, 'bodega_id' => $bodegaId], ['cantidad' => 0]);

        return Existencia::where(['producto_id' => $productoId, 'bodega_id' => $bodegaId])->lockForUpdate()->first();
    }

    private function recalcularCosto(Producto $p, float $cantidad, float $costo): void
    {
        $stock = (float) Existencia::where('producto_id', $p->id)->sum('cantidad');
        $stock = max($stock, 0);
        $nuevo = ($stock + $cantidad) > 0
            ? (($stock * (float) $p->costo_promedio) + ($cantidad * $costo)) / ($stock + $cantidad)
            : $costo;
        $p->update(['costo_promedio' => round($nuevo, 4)]);
    }

    private function validarBodegas(string $efecto, array $d): void
    {
        if (in_array($efecto, ['salida', 'traslado']) && empty($d['bodega_origen_id'])) {
            throw ValidationException::withMessages(['bodega_origen_id' => 'Indica de qué bodega sale.']);
        }
        if (in_array($efecto, ['entrada', 'traslado']) && empty($d['bodega_destino_id'])) {
            throw ValidationException::withMessages(['bodega_destino_id' => 'Indica a qué bodega entra.']);
        }
        if ($efecto === 'traslado' && $d['bodega_origen_id'] == $d['bodega_destino_id']) {
            throw ValidationException::withMessages(['bodega_destino_id' => 'La bodega de destino debe ser distinta.']);
        }
    }

    private function avisarPendiente(Movimiento $mov): void
    {
        $destino = User::activos()->permission('movimientos.aprobar')->where('id', '!=', $mov->user_id)->get();
        Notification::send($destino, new Aviso(
            'Ajuste por aprobar',
            "{$mov->folio} · {$mov->nombreTipo()} registrado por {$mov->user->name}",
            route('movimientos.show', $mov)
        ));
    }

    private function avisarBajoMinimo(Movimiento $mov): void
    {
        if (! in_array($mov->efecto, ['salida', 'traslado'])) {
            return;
        }
        $bajos = Producto::whereIn('id', $mov->lineas->pluck('producto_id'))
            ->where('stock_minimo', '>', 0)->withSum('existencias', 'cantidad')->get()
            ->filter->bajoMinimo();
        if ($bajos->isEmpty()) {
            return;
        }
        $destino = User::activos()->permission('inventario.gestionar')->get();
        foreach ($bajos as $p) {
            Notification::send($destino, new Aviso(
                'Stock bajo el mínimo',
                "{$p->etiqueta()}: quedan ".rtrim(rtrim(number_format($p->stockTotal(), 3, '.', ''), '0'), '.').' '.$p->unidad->abreviatura,
                route('productos.show', $p)
            ));
        }
    }
}
