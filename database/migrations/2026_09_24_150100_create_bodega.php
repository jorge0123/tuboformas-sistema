<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bodega de repuestos de mantenimiento: una sola bodega, la existencia vive en el producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('nit', 30)->nullable();
            $table->string('contacto', 100)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('direccion')->nullable();
            $table->json('tipos')->nullable();     // repuestos, servicios, insumos
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 200);
            $table->string('tipo', 30)->index();  // repuesto, insumo
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_producto')->nullOnDelete();
            $table->string('medida', 50)->nullable();
            $table->foreignId('unidad_id')->constrained('unidades');
            $table->decimal('existencia', 14, 3)->default(0);
            $table->decimal('stock_minimo', 14, 3)->default(0);
            $table->decimal('costo_promedio', 14, 4)->default(0);
            $table->string('ubicacion', 100)->nullable();
            $table->string('foto')->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('proveedor_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('codigo_proveedor', 60)->nullable();
            $table->decimal('precio', 14, 4)->nullable();
            $table->unsignedSmallInteger('dias_entrega')->nullable();
            $table->timestamps();
            $table->unique(['proveedor_id', 'producto_id']);
        });

        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('tipo', 30)->index();
            $table->string('efecto', 10);          // entrada, salida
            $table->string('estado', 20)->index(); // pendiente, confirmado, rechazado, anulado
            $table->date('fecha')->index();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->unsignedBigInteger('maquina_id')->nullable()->index();
            $table->unsignedBigInteger('orden_trabajo_id')->nullable()->index();
            $table->foreignId('movimiento_origen_id')->nullable()->constrained('movimientos'); // reverso de…
            $table->string('documento', 60)->nullable();   // factura, vale
            $table->string('referencia', 150)->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamp('aprobado_at')->nullable();
            $table->string('motivo_rechazo')->nullable();
            $table->timestamps();
        });

        Schema::create('movimiento_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('movimientos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 4)->nullable();
            $table->decimal('saldo', 14, 3)->nullable();   // existencia resultante (kárdex)
            $table->string('notas')->nullable();
        });

        Schema::create('conteos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('estado', 20)->default('abierto'); // abierto, aplicado, cancelado
            $table->date('fecha');
            $table->text('notas')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('aplicado_por')->nullable()->constrained('users');
            $table->timestamp('aplicado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conteo_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_id')->constrained('conteos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad_sistema', 14, 3);
            $table->decimal('cantidad_contada', 14, 3)->nullable();
            $table->foreignId('contado_por')->nullable()->constrained('users');
            $table->timestamp('contado_at')->nullable();
            $table->unique(['conteo_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        foreach (['conteo_lineas', 'conteos', 'movimiento_lineas', 'movimientos',
            'proveedor_producto', 'productos', 'proveedores'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
