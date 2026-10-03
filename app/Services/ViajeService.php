<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Maquina;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Viaje;
use App\Notifications\Aviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Viaje de entrega (docs/DISENO.md §2.8): un camión y su piloto llevan varios pedidos en orden de
 * paradas. Al salir se despachan todos (cada uno descuenta su inventario); el piloto confirma cada
 * parada o la regresa a bodega, y el viaje se cierra solo cuando ya no quedan paradas pendientes.
 */
class ViajeService
{
    public function __construct(private PedidoService $pedidos) {}

    /** @param  int[]  $pedidoIds  en el orden de las paradas */
    public function salir(int $vehiculoId, int $pilotoId, array $pedidoIds, ?string $notas, User $usuario): Viaje
    {
        $pedidoIds = array_values(array_unique(array_map('intval', $pedidoIds)));
        if (! $pedidoIds) {
            throw ValidationException::withMessages(['pedidos' => 'Elige al menos un pedido.']);
        }
        if ($enRuta = Viaje::where('vehiculo_id', $vehiculoId)->where('estado', 'en_ruta')->first()) {
            throw ValidationException::withMessages(['vehiculo_id' => Maquina::find($vehiculoId)->etiqueta()." sigue en ruta con el viaje {$enRuta->folio}. Ciérralo primero."]);
        }

        $viaje = DB::transaction(function () use ($vehiculoId, $pilotoId, $pedidoIds, $notas, $usuario) {
            $viaje = Viaje::create([
                'folio' => Folios::siguiente('VIA', 5), 'vehiculo_id' => $vehiculoId, 'piloto_id' => $pilotoId,
                'estado' => 'en_ruta', 'salida_at' => now(), 'notas' => $notas, 'user_id' => $usuario->id,
            ]);
            $pedidos = Pedido::with(['cliente', 'movimiento'])->whereIn('id', $pedidoIds)->lockForUpdate()->get()->keyBy('id');
            foreach ($pedidoIds as $i => $id) {
                abort_unless($pedidos->has($id), 404);
                // Si a uno no le alcanza la existencia, no sale ninguno (la transacción se deshace).
                $this->pedidos->despacharEnViaje($pedidos[$id], $viaje, $i + 1, count($pedidoIds), $usuario);
            }

            return $viaje;
        });

        Auditoria::registrar('crear', $viaje, "{$viaje->folio} · {$viaje->vehiculo->codigo} · ".count($pedidoIds).' pedidos');
        $paradas = $viaje->pedidos()->with('cliente')->get();
        $this->avisar([$viaje->piloto_id], $usuario, "Tienes un viaje: {$viaje->folio}",
            count($pedidoIds).' '.(count($pedidoIds) === 1 ? 'entrega' : 'entregas')." en {$viaje->vehiculo->codigo}: ".$paradas->map(fn ($p) => $p->cliente->municipio ?: $p->cliente->nombre)->unique()->join(' → '), $viaje);

        return $viaje;
    }

    public function entregar(Pedido $pedido, array $d, User $usuario): void
    {
        $this->exigirParada($pedido);
        $this->pedidos->entregar($pedido, $d, $usuario);
        $this->cerrarSiTermino($pedido->viaje, $usuario);
    }

    public function noEntregado(Pedido $pedido, string $motivo, User $usuario): void
    {
        $this->exigirParada($pedido);
        $this->pedidos->regresar($pedido, $motivo, $usuario);
        $this->cerrarSiTermino($pedido->viaje, $usuario);
    }

    /** Sin paradas pendientes, el viaje termina y bodega recibe el resumen. */
    private function cerrarSiTermino(Viaje $viaje, User $usuario): void
    {
        $viaje->refresh();
        if ($viaje->estado !== 'en_ruta' || $viaje->pendientes()->exists()) {
            return;
        }
        $viaje->update(['estado' => 'terminado', 'regreso_at' => now()]);
        $entregados = $viaje->pedidos()->where('estado', 'entregado')->count();
        $regresan = $viaje->pedidos()->where('estado', 'listo')->count();
        $resumen = "$entregados ".($entregados === 1 ? 'entregado' : 'entregados').($regresan ? ", $regresan ".($regresan === 1 ? 'regresa' : 'regresan').' a bodega' : '');
        Auditoria::registrar('terminar', $viaje, "{$viaje->folio}: $resumen");
        $destino = User::activos()->permission('pedidos.preparar')->pluck('id')->push($viaje->user_id, $viaje->piloto_id)->all();
        $this->avisar($destino, $usuario, "✓ Viaje {$viaje->folio} terminado", "{$viaje->vehiculo->codigo} · $resumen.", $viaje);
    }

    private function exigirParada(Pedido $pedido): void
    {
        if (! $pedido->viaje || $pedido->estado !== 'en_ruta') {
            throw ValidationException::withMessages(['estado' => "{$pedido->folio} ya no está pendiente en este viaje. Recarga la pantalla."]);
        }
    }

    private function avisar(array $ids, User $autor, string $titulo, string $mensaje, Viaje $viaje): void
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => $id === (int) $autor->id);
        if ($ids->isNotEmpty()) {
            Notification::send(User::whereIn('id', $ids)->activos()->get(), new Aviso($titulo, $mensaje, route('viajes.show', $viaje)));
        }
    }
}
