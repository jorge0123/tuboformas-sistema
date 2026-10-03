<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Maquina;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\ProductoPresentacion;
use App\Models\User;
use App\Models\Viaje;
use App\Notifications\Aviso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de un pedido de cliente (docs/DISENO.md §2.8):
 * ventas lo ingresa → bodega lo toma y arma línea por línea → listo → se despacha (aquí sale del
 * inventario con un movimiento "salida_despacho") → entregado. Cada paso queda en el seguimiento
 * y avisa a quien corresponde.
 */
class PedidoService
{
    public function __construct(private InventarioService $inv) {}

    public function crear(array $datos, array $lineas, User $usuario): Pedido
    {
        $pedido = DB::transaction(function () use ($datos, $lineas, $usuario) {
            $pedido = Pedido::create($datos + [
                'folio' => Folios::siguiente('PED'),
                'estado' => 'nuevo',
                'vendedor_id' => $usuario->id,
            ]);
            $this->guardarLineas($pedido, $lineas);
            $this->seguir($pedido, $usuario, 'nuevo', 'Pedido ingresado.');

            return $pedido;
        });
        Auditoria::registrar('crear', $pedido, "{$pedido->folio} · {$pedido->cliente->nombre}");

        $this->avisarBodega($pedido, $usuario, ($pedido->prioridad === 'urgente' ? 'Pedido URGENTE ' : 'Nuevo pedido ').$pedido->folio,
            "{$pedido->cliente->nombre} · entrega {$pedido->fecha_entrega->format('d/m/Y')}".($pedido->jornada ? ' ('.mb_strtolower(Pedido::JORNADAS[$pedido->jornada]).')' : '')
            .' · '.$pedido->lineas()->count().' productos');

        return $pedido;
    }

    /** Solo mientras nadie lo ha empezado a armar. */
    public function actualizar(Pedido $pedido, array $datos, array $lineas, User $usuario): void
    {
        if ($pedido->estado !== 'nuevo') {
            throw ValidationException::withMessages(['estado' => 'Bodega ya empezó a armar este pedido; ya no se puede editar. Pide que lo cancelen y crea uno nuevo.']);
        }
        DB::transaction(function () use ($pedido, $datos, $lineas, $usuario) {
            $pedido->update($datos);
            $pedido->lineas()->delete();
            $this->guardarLineas($pedido, $lineas);
            $this->seguir($pedido, $usuario, null, 'Pedido modificado.');
        });
        Auditoria::registrar('editar', $pedido, $pedido->folio);
        $this->avisarBodega($pedido, $usuario, "{$pedido->folio} modificado", "{$pedido->cliente->nombre}: revisa los cambios antes de armarlo.");
    }

    /** Alguien de bodega lo toma: queda a su nombre y "en preparación". */
    public function tomar(Pedido $pedido, User $usuario): void
    {
        $this->exigir($pedido, ['nuevo']);
        $pedido->update(['estado' => 'preparando', 'preparado_por' => $usuario->id, 'preparando_at' => now()]);
        $this->seguir($pedido, $usuario, 'preparando', "{$usuario->name} está armando el pedido.");
        $this->avisar($pedido, $usuario, [$pedido->vendedor_id], "{$pedido->folio} en preparación", "{$usuario->name} está armando el pedido de {$pedido->cliente->nombre}.");
    }

    /** Marca (o desmarca) una línea como armada; cantidad en unidades base si se armó menos. */
    public function marcarLinea(PedidoLinea $linea, bool $preparada, ?float $cantidad, User $usuario): void
    {
        $pedido = $linea->pedido;
        $this->exigir($pedido, ['nuevo', 'preparando']);
        if ($pedido->estado === 'nuevo') {
            $this->tomar($pedido, $usuario);
        }
        if ($cantidad !== null && ($cantidad < 0 || $cantidad > (float) $linea->cantidad_base)) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad armada debe estar entre 0 y lo pedido.']);
        }
        $linea->update([
            'preparada' => $preparada,
            'cantidad_preparada' => $preparada ? ($cantidad ?? $linea->cantidad_base) : null,
        ]);
    }

    public function marcarListo(Pedido $pedido, User $usuario, ?string $nota = null): void
    {
        $this->exigir($pedido, ['preparando']);
        $faltan = $pedido->lineas()->where('preparada', false)->count();
        if ($faltan) {
            throw ValidationException::withMessages(['lineas' => "Faltan $faltan ".($faltan === 1 ? 'producto' : 'productos').' por marcar como armados.']);
        }
        if ((float) $pedido->lineas()->sum('cantidad_preparada') <= 0) {
            throw ValidationException::withMessages(['lineas' => 'No se armó ningún producto. Si no hay existencia, cancela el pedido.']);
        }
        $pedido->update(['estado' => 'listo', 'listo_at' => now()]);
        $incompletas = $pedido->lineas()->with('producto')->get()->filter->incompleta();
        $texto = 'Pedido armado'.($incompletas->isNotEmpty() ? ' (incompleto: '.$incompletas->map(fn ($l) => $l->producto->nombre)->join(', ').')' : '').'.';
        $this->seguir($pedido, $usuario, 'listo', trim($texto.' '.$nota));
        $mensaje = "{$pedido->cliente->nombre}: ".($pedido->tipo_entrega === 'recoge' ? 'listo para que lo recojan.' : 'listo para despachar.').($incompletas->isNotEmpty() ? ' Va incompleto.' : '');
        // Ventas se entera y bodega sabe que ya puede despacharse.
        $this->avisar($pedido, $usuario, [$pedido->vendedor_id], "{$pedido->folio} listo", $mensaje);
        $this->avisarBodega($pedido, $usuario, "{$pedido->folio} listo para despachar", $mensaje);
    }

    /**
     * Sale de bodega: se descuenta del inventario con un despacho ligado al pedido.
     * En ruta propia: vehiculo_id (Máquinas → Vehículos) y piloto_id (usuario piloto).
     * Por transporte: empresa (vehiculo) y número de guía (documento).
     */
    public function despachar(Pedido $pedido, array $d, User $usuario): void
    {
        $this->exigir($pedido, ['listo']);
        if ($pedido->tipo_entrega === 'recoge') {
            throw ValidationException::withMessages(['estado' => 'Este pedido lo recoge el cliente: márcalo como entregado.']);
        }
        $piloto = ! empty($d['piloto_id']) ? User::find($d['piloto_id']) : null;
        $vehiculo = ! empty($d['vehiculo_id']) ? Maquina::find($d['vehiculo_id']) : null;
        DB::transaction(function () use ($pedido, $d, $usuario, $piloto, $vehiculo) {
            $mov = $this->registrarSalida($pedido, $usuario, $d['guia'] ?? null);
            $pedido->update([
                'estado' => 'en_ruta', 'en_ruta_at' => now(), 'despachado_por' => $usuario->id, 'movimiento_id' => $mov->id,
                'vehiculo_id' => $vehiculo?->id, 'piloto_id' => $piloto?->id, 'viaje_id' => null, 'orden_parada' => null,
                'vehiculo' => $vehiculo ? null : ($d['empresa'] ?? null), 'piloto' => null, 'documento' => $d['guia'] ?? null,
            ]);
            $con = $vehiculo ? ' en '.$pedido->nombreVehiculo() : (! empty($d['empresa']) ? " por {$d['empresa']}".(! empty($d['guia']) ? " (guía {$d['guia']})" : '') : '');
            $this->seguir($pedido, $usuario, 'en_ruta', "Salió de bodega{$con}".($piloto ? " con {$piloto->name}" : '').". Inventario descontado ({$mov->folio}).");
        });
        $this->avisar($pedido, $usuario, [$pedido->vendedor_id], "{$pedido->folio} en ruta", "Va en camino a {$pedido->cliente->nombre}".($piloto ? " con {$piloto->name}" : '').'.');
        if ($piloto) {
            $this->avisar($pedido, $usuario, [$piloto->id], "Tienes una entrega: {$pedido->folio}",
                "{$pedido->cliente->nombre} · ".($pedido->direccion_entrega ?: 'ver dirección').($pedido->vehiculoAsignado ? ' · '.$pedido->vehiculoAsignado->codigo : ''));
        }
    }

    /** Sale como una parada de un viaje (el viaje avisa al piloto una sola vez). */
    public function despacharEnViaje(Pedido $pedido, Viaje $viaje, int $parada, int $total, User $usuario): void
    {
        $this->exigir($pedido, ['listo']);
        if ($pedido->tipo_entrega !== 'ruta') {
            throw ValidationException::withMessages(['pedidos' => "{$pedido->folio} no es de entrega en ruta."]);
        }
        $mov = $this->registrarSalida($pedido, $usuario);
        $pedido->update([
            'estado' => 'en_ruta', 'en_ruta_at' => now(), 'despachado_por' => $usuario->id, 'movimiento_id' => $mov->id,
            'vehiculo_id' => $viaje->vehiculo_id, 'piloto_id' => $viaje->piloto_id, 'viaje_id' => $viaje->id, 'orden_parada' => $parada,
            'vehiculo' => null, 'piloto' => null, 'documento' => null,
        ]);
        $this->seguir($pedido, $usuario, 'en_ruta', "Salió en el viaje {$viaje->folio} (parada $parada de $total) con {$viaje->piloto->name}. Inventario descontado ({$mov->folio}).");
        $this->avisar($pedido, $usuario, [$pedido->vendedor_id], "{$pedido->folio} en ruta", "Va en camino a {$pedido->cliente->nombre} con {$viaje->piloto->name} (parada $parada de $total).");
    }

    /**
     * No se pudo entregar: regresa a bodega. Se anula su salida (el inventario vuelve) y queda
     * "listo" para salir en otro viaje.
     */
    public function regresar(Pedido $pedido, string $motivo, User $usuario): void
    {
        $this->exigir($pedido, ['en_ruta']);
        DB::transaction(function () use ($pedido, $motivo, $usuario) {
            if ($pedido->movimiento) {
                $this->inv->anular($pedido->movimiento, $usuario, "No se entregó {$pedido->folio}: $motivo", avisar: false);
            }
            $pedido->update(['estado' => 'listo', 'movimiento_id' => null, 'en_ruta_at' => null, 'despachado_por' => null]);
            $this->seguir($pedido, $usuario, 'listo', "No se pudo entregar: $motivo. Regresa a bodega; el inventario se repuso.");
        });
        $this->avisar($pedido, $usuario, [$pedido->vendedor_id, $pedido->preparado_por], "{$pedido->folio} no se entregó", "{$pedido->cliente->nombre}: $motivo. Regresa a bodega.");
    }

    public function entregar(Pedido $pedido, array $d, User $usuario): void
    {
        $this->exigir($pedido, $pedido->tipo_entrega === 'recoge' ? ['listo'] : ['en_ruta']);
        DB::transaction(function () use ($pedido, $d, $usuario) {
            $cambios = ['estado' => 'entregado', 'entregado_at' => now(), 'recibido_por' => $d['recibido_por'] ?? null];
            if (! $pedido->movimiento_id) { // cliente recogió: aquí sale del inventario
                $mov = $this->registrarSalida($pedido, $usuario);
                $cambios += ['movimiento_id' => $mov->id, 'despachado_por' => $usuario->id];
            }
            $pedido->update($cambios);
            $this->seguir($pedido, $usuario, 'entregado', trim('Entregado'.(! empty($d['recibido_por']) ? ", recibió {$d['recibido_por']}" : '').'. '.($d['nota'] ?? '')));
        });
        // Terminó: se enteran ventas, quien lo armó, quien lo despachó y el piloto.
        $pedido->refresh();
        $this->avisar($pedido, $usuario, $this->involucrados($pedido), "✓ {$pedido->folio} entregado",
            "{$pedido->cliente->nombre}".(! empty($d['recibido_por']) ? " · recibió {$d['recibido_por']}" : '').' · '.now()->format('d/m H:i').'.');
    }

    public function cancelar(Pedido $pedido, string $motivo, User $usuario): void
    {
        if (! in_array($pedido->estado, ['nuevo', 'preparando', 'listo'], true)) {
            throw ValidationException::withMessages(['estado' => 'Un pedido que ya salió de bodega no se cancela aquí: anula su despacho en Movimientos.']);
        }
        $pedido->update(['estado' => 'cancelado', 'cancelado_at' => now(), 'motivo_cancelacion' => $motivo]);
        $this->seguir($pedido, $usuario, 'cancelado', "Cancelado: $motivo");
        Auditoria::registrar('cancelar', $pedido, "{$pedido->folio}: $motivo");
        $estabaNuevo = ! $pedido->preparado_por;
        $this->avisar($pedido, $usuario, $this->involucrados($pedido), "{$pedido->folio} cancelado", "{$pedido->cliente->nombre}: $motivo");
        if ($estabaNuevo) {
            $this->avisarBodega($pedido, $usuario, "{$pedido->folio} cancelado", "{$pedido->cliente->nombre}: ya no hay que armarlo. $motivo");
        }
    }

    public function comentar(Pedido $pedido, string $texto, User $usuario): void
    {
        $this->seguir($pedido, $usuario, null, $texto);
        $this->avisar($pedido, $usuario, $this->involucrados($pedido), "Comentario en {$pedido->folio}", "{$usuario->name}: ".mb_strimwidth($texto, 0, 120, '…'));
    }

    // ── Internos ─────────────────────────────────────────────────────────

    private function guardarLineas(Pedido $pedido, array $lineas): void
    {
        if (! $lineas) {
            throw ValidationException::withMessages(['lineas' => 'Agrega al menos un producto.']);
        }
        foreach ($lineas as $l) {
            $factor = 1.0;
            if (! empty($l['presentacion_id'])) {
                $factor = (float) ProductoPresentacion::where('producto_id', $l['producto_id'])->findOrFail($l['presentacion_id'])->factor;
            }
            $pedido->lineas()->create([
                'producto_id' => $l['producto_id'], 'presentacion_id' => ($l['presentacion_id'] ?? null) ?: null,
                'cantidad' => $l['cantidad'], 'factor' => $factor, 'cantidad_base' => round($l['cantidad'] * $factor, 3),
                'notas' => $l['notas'] ?? null,
            ]);
        }
    }

    /** Despacho por lo que realmente se armó; si una línea va completa se registra en su presentación. */
    private function registrarSalida(Pedido $pedido, User $usuario, ?string $documento = null)
    {
        $lineas = $pedido->lineas()->where('cantidad_preparada', '>', 0)->get()->map(fn ($l) => (float) $l->cantidad_preparada === (float) $l->cantidad_base && $l->presentacion_id
            ? ['producto_id' => $l->producto_id, 'presentacion_id' => $l->presentacion_id, 'cantidad' => (float) $l->cantidad]
            : ['producto_id' => $l->producto_id, 'cantidad' => (float) $l->cantidad_preparada])->all();

        return $this->inv->registrar([
            'tipo' => 'salida_despacho', 'fecha' => today(), 'bodega_origen_id' => $pedido->bodega_id,
            // Referencia para contabilidad: la guía del transporte, la OC del cliente o el folio del pedido.
            'documento' => $documento ?: ($pedido->orden_compra ?: $pedido->folio), 'referencia' => "Pedido {$pedido->folio} · {$pedido->cliente->nombre}",
        ], $lineas, $usuario);
    }

    private function exigir(Pedido $pedido, array $estados): void
    {
        if (! in_array($pedido->estado, $estados, true)) {
            throw ValidationException::withMessages(['estado' => 'El pedido está «'.Pedido::ESTADOS[$pedido->estado].'»; alguien más ya lo movió. Recarga la pantalla.']);
        }
    }

    private function seguir(Pedido $pedido, User $usuario, ?string $estado, ?string $texto): void
    {
        $pedido->seguimientos()->create(['user_id' => $usuario->id, 'estado' => $estado, 'texto' => $texto]);
    }

    private function avisarBodega(Pedido $pedido, User $autor, string $titulo, string $mensaje): void
    {
        Notification::send(
            User::activos()->permission('pedidos.preparar')->where('id', '!=', $autor->id)->get(),
            new Aviso($titulo, $mensaje, route('pedidos.show', $pedido))
        );
    }

    /** Quienes participan en el pedido: ventas, quien lo armó, quien lo despachó y el piloto. */
    private function involucrados(Pedido $pedido): array
    {
        return [$pedido->vendedor_id, $pedido->preparado_por, $pedido->despachado_por, $pedido->piloto_id];
    }

    /** Avisa a esas personas una sola vez, sin incluir a quien hizo la acción ni a usuarios inactivos. */
    private function avisar(Pedido $pedido, User $autor, array $ids, string $titulo, string $mensaje): void
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => $id === (int) $autor->id);
        if ($ids->isNotEmpty()) {
            Notification::send(User::whereIn('id', $ids)->activos()->get(), new Aviso($titulo, $mensaje, route('pedidos.show', $pedido)));
        }
    }
}
