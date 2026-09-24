<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maquinas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->nullable();          // "No." del Excel
            $table->string('codigo', 30)->nullable()->unique();     // Designación: AF1, CA1…
            $table->string('nombre', 150);                          // Descripción
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('serie', 100)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('ubicacion', 100)->nullable();
            $table->char('criticidad', 1)->default('B');            // A, B, C
            $table->string('estado', 20)->default('operativa')->index();
            $table->decimal('horometro', 12, 1)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });

        // Bloques de la hoja técnica: "Inyectora", "Motor eléctrico trifásico", "Bomba"…
        Schema::create('maquina_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained('maquinas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->json('especificaciones')->nullable(); // [{clave, valor}]
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Tablas como "Lista de Resistencias".
        Schema::create('maquina_partes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained('maquinas')->cascadeOnDelete();
            $table->string('grupo', 100)->nullable();       // Resistencias, Filtros, Fajas…
            $table->string('especificacion', 200);
            $table->string('dimensiones', 150)->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('planes_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->cascadeOnDelete();
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->json('checklist')->nullable();          // ["Revisar…", "Lubricar…"]
            $table->string('tipo', 20)->default('preventivo');
            $table->foreignId('especialidad_id')->nullable()->constrained('especialidades')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prioridad', 10)->default('media');
            $table->unsignedSmallInteger('frecuencia_valor');
            $table->string('frecuencia_unidad', 10);        // dias, semanas, meses
            $table->date('proxima_fecha');
            $table->unsignedSmallInteger('dias_anticipacion')->default(3);
            $table->decimal('duracion_estimada', 6, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('planes_mantenimiento')->nullOnDelete();
            $table->string('tipo', 20)->index();            // preventivo, correctivo, predictivo, mejora, proyecto
            $table->foreignId('especialidad_id')->nullable()->constrained('especialidades')->nullOnDelete();
            $table->string('prioridad', 10)->default('media')->index();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('solicitante_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->timestamp('completada_at')->nullable();
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->decimal('horas_trabajo', 8, 2)->nullable();
            $table->boolean('detuvo_maquina')->default(false);
            $table->decimal('horas_paro', 8, 2)->nullable();
            $table->string('motivo_espera')->nullable();
            $table->json('checklist')->nullable();          // [{texto, hecho}]
            $table->text('trabajo_realizado')->nullable();
            $table->unsignedSmallInteger('orden_kanban')->default(0);
            $table->timestamps();
        });

        Schema::create('orden_trabajo_ayudantes', function (Blueprint $table) {
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['orden_trabajo_id', 'user_id']);
        });

        // Línea de tiempo de la OT: cada avance, cambio de estado, comentario o pregunta.
        Schema::create('ot_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('tipo', 20)->default('comentario'); // comentario, avance, estado, asignacion, sistema
            $table->text('texto')->nullable();
            $table->unsignedTinyInteger('progreso')->nullable();   // progreso después de este registro
            $table->string('estado', 20)->nullable();              // estado después de este registro
            $table->decimal('horas', 8, 2)->nullable();            // horas trabajadas en este registro
            $table->timestamps();
        });

        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained('maquinas')->cascadeOnDelete();
            $table->foreignId('orden_trabajo_id')->nullable()->unique()->constrained('ordenes_trabajo')->nullOnDelete();
            $table->date('fecha')->index();
            $table->string('tipo', 20);
            $table->string('componente', 100)->nullable();
            $table->text('trabajo_realizado');
            $table->decimal('horas', 8, 2)->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsable_nombre', 100)->nullable(); // históricos: "Bryan Lemus"
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->boolean('garantia')->default(false);
            $table->decimal('costo', 12, 2)->nullable();
            $table->decimal('horometro', 12, 1)->nullable();
            $table->text('comentarios')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('herramientas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            $table->string('categoria', 60)->nullable();
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->string('serie', 60)->nullable();
            $table->string('estado', 20)->default('disponible')->index();
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('costo', 12, 2)->nullable();
            $table->date('fecha_compra')->nullable();
            $table->string('foto')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('herramienta_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('herramienta_id')->constrained('herramientas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('entregado_por')->constrained('users');
            $table->timestamp('entregado_at');
            $table->string('estado_entrega', 20)->default('bueno');
            $table->foreignId('recibido_por')->nullable()->constrained('users');
            $table->timestamp('devuelto_at')->nullable();
            $table->string('estado_devolucion', 20)->nullable(); // bueno, danado, perdido
            $table->string('notas')->nullable();
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreign('maquina_id')->references('id')->on('maquinas')->nullOnDelete();
            $table->foreign('orden_trabajo_id')->references('id')->on('ordenes_trabajo')->nullOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('especialidad_id')->references('id')->on('especialidades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['especialidad_id']));
        Schema::table('movimientos', function (Blueprint $t) {
            $t->dropForeign(['maquina_id']);
            $t->dropForeign(['orden_trabajo_id']);
        });
        foreach (['herramienta_asignaciones', 'herramientas', 'bitacoras', 'ot_seguimientos',
            'orden_trabajo_ayudantes', 'ordenes_trabajo', 'planes_mantenimiento',
            'maquina_partes', 'maquina_componentes', 'maquinas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
