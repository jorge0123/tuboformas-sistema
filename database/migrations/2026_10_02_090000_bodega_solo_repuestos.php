<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El sistema queda en mantenimiento + bodega de repuestos (docs/DISENO.md §2.7).
 * Convierte una base que ya tenía la bodega general: quita pedidos, viajes y clientes,
 * materia prima y producto terminado, presentaciones, colores y las varias bodegas
 * (la existencia pasa al producto). En una instalación nueva no hace nada.
 */
return new class extends Migration
{
    private const TIPOS_MOVIMIENTO = ['entrada_compra', 'consumo_mantenimiento', 'ajuste_entrada', 'ajuste_salida', 'reverso'];

    public function up(): void
    {
        foreach (['pedido_seguimientos', 'pedido_lineas', 'pedidos', 'viajes', 'clientes'] as $t) {
            Schema::dropIfExists($t);
        }
        if (Schema::hasColumn('users', 'es_piloto')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('es_piloto'));
        }

        if (! Schema::hasTable('existencias')) {
            return;
        }

        Schema::table('productos', fn (Blueprint $t) => $t->decimal('existencia', 14, 3)->default(0)->after('unidad_id'));
        DB::statement('update productos p set existencia = (select coalesce(sum(e.cantidad), 0) from existencias e where e.producto_id = p.id)');

        // Movimientos de producción, despacho y traslados (con sus reversos) se van completos.
        $fuera = DB::table('movimientos')->whereNotIn('tipo', self::TIPOS_MOVIMIENTO)->pluck('id');
        $fuera = $fuera->merge(DB::table('movimientos')->whereIn('movimiento_origen_id', $fuera)->pluck('id'));
        DB::table('movimientos')->whereIn('movimiento_origen_id', $fuera)->delete();
        DB::table('movimientos')->whereIn('id', $fuera)->delete();

        // Solo quedan repuestos e insumos de mantenimiento.
        $otros = DB::table('productos')->whereNotIn('tipo', ['repuesto', 'insumo'])->pluck('id');
        DB::table('movimiento_lineas')->whereIn('producto_id', $otros)->delete();
        DB::table('conteo_lineas')->whereIn('producto_id', $otros)->delete();
        DB::table('maquina_partes')->whereIn('producto_id', $otros)->update(['producto_id' => null]);
        DB::table('movimientos')->whereNotExists(fn ($q) => $q->from('movimiento_lineas')->whereColumn('movimiento_lineas.movimiento_id', 'movimientos.id'))
            ->whereNotIn('id', DB::table('movimientos')->whereNotNull('movimiento_origen_id')->pluck('movimiento_origen_id'))->delete();
        DB::table('conteos')->whereNotExists(fn ($q) => $q->from('conteo_lineas')->whereColumn('conteo_lineas.conteo_id', 'conteos.id'))->delete();
        DB::table('existencias')->whereIn('producto_id', $otros)->delete();
        DB::table('productos')->whereIn('id', $otros)->delete();

        // Líneas en unidad base y un solo saldo.
        Schema::table('movimiento_lineas', fn (Blueprint $t) => $t->decimal('saldo', 14, 3)->nullable()->after('costo_unitario'));
        DB::statement('update movimiento_lineas set cantidad = cantidad_base, saldo = coalesce(saldo_destino, saldo_origen)');
        Schema::table('movimiento_lineas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('presentacion_id');
            $t->dropColumn(['factor', 'cantidad_base', 'saldo_origen', 'saldo_destino']);
        });
        DB::table('movimientos')->where('efecto', 'traslado')->delete();
        Schema::table('movimientos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('bodega_origen_id');
            $t->dropConstrainedForeignId('bodega_destino_id');
        });
        Schema::table('conteos', fn (Blueprint $t) => $t->dropConstrainedForeignId('bodega_id'));

        Schema::dropIfExists('existencias');
        Schema::dropIfExists('producto_presentaciones');
        Schema::dropIfExists('bodegas');
        if (Schema::hasColumn('productos', 'color')) {
            Schema::table('productos', fn (Blueprint $t) => $t->dropColumn('color'));
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: los datos de la bodega general se recuperan del respaldo.
    }
};
