<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos de clientes (órdenes de entrega): ventas los ingresa, bodega los arma y despacha.
 * Ver docs/DISENO.md §2.8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('nit', 30)->nullable();
            $table->string('contacto', 100)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('direccion')->nullable();
            $table->string('municipio', 100)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('estado', 20)->default('nuevo')->index(); // nuevo, preparando, listo, en_ruta, entregado, cancelado
            $table->string('prioridad', 10)->default('normal');      // normal, urgente
            $table->string('tipo_entrega', 20)->default('ruta');     // ruta, recoge, transporte
            $table->date('fecha_entrega')->index();
            $table->string('jornada', 10)->nullable();               // manana, tarde
            $table->string('direccion_entrega')->nullable();
            $table->string('contacto_nombre', 100)->nullable();
            $table->string('contacto_telefono', 50)->nullable();
            $table->string('orden_compra', 60)->nullable();          // No. de OC del cliente
            $table->string('condicion_pago', 20)->nullable();        // contado, credito
            $table->text('notas')->nullable();
            $table->foreignId('bodega_id')->constrained('bodegas');
            $table->foreignId('vendedor_id')->constrained('users');
            $table->foreignId('preparado_por')->nullable()->constrained('users');
            $table->foreignId('despachado_por')->nullable()->constrained('users');
            $table->string('documento', 60)->nullable();             // factura / envío
            $table->string('vehiculo', 60)->nullable();
            $table->string('piloto', 100)->nullable();
            $table->string('recibido_por', 100)->nullable();
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos')->nullOnDelete();
            $table->string('motivo_cancelacion')->nullable();
            $table->timestamp('preparando_at')->nullable();
            $table->timestamp('listo_at')->nullable();
            $table->timestamp('en_ruta_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pedido_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('presentacion_id')->nullable()->constrained('producto_presentaciones')->nullOnDelete();
            $table->decimal('cantidad', 14, 3);          // en la presentación pedida
            $table->decimal('factor', 14, 3)->default(1);
            $table->decimal('cantidad_base', 14, 3);
            $table->boolean('preparada')->default(false);
            $table->decimal('cantidad_preparada', 14, 3)->nullable(); // unidades base que se armaron
            $table->string('notas')->nullable();
        });

        Schema::create('pedido_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('estado', 20)->nullable();
            $table->text('texto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pedido_seguimientos', 'pedido_lineas', 'pedidos', 'clientes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
