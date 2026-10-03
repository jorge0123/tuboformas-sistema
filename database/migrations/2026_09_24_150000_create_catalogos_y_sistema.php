<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos de presentación de los roles de spatie (name = clave interna).
        Schema::table('roles', function (Blueprint $table) {
            $table->string('nombre', 100)->nullable()->after('name');
            $table->string('descripcion')->nullable()->after('nombre');
            $table->boolean('es_sistema')->default(false)->after('descripcion');
        });

        // Catálogos simples: todos con nombre + activo.
        foreach (['areas', 'especialidades', 'categorias_producto', 'unidades'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table) use ($tabla) {
                $table->id();
                $table->string('nombre', 100)->unique();
                if ($tabla === 'unidades') {
                    $table->string('abreviatura', 20);
                }
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // Numeración correlativa (OT-000001, MOV-000001…), bloqueada por fila.
        Schema::create('folios', function (Blueprint $table) {
            $table->string('clave', 20)->primary();
            $table->unsignedBigInteger('ultimo')->default(0);
        });

        // Archivos adjuntos de cualquier entidad (fotos, manuales, diagramas, evidencias).
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->morphs('adjuntable');
            $table->string('categoria', 30)->default('foto'); // foto, manual, diagrama, evidencia, documento
            $table->string('nombre');
            $table->string('ruta');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 50);          // crear, editar, eliminar, aprobar, anular, login…
            $table->string('entidad', 60)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('descripcion')->nullable();
            $table->json('datos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['entidad', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('archivos');
        Schema::dropIfExists('folios');
        foreach (['unidades', 'categorias_producto', 'especialidades', 'areas'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('roles', fn (Blueprint $t) => $t->dropColumn(['nombre', 'descripcion', 'es_sistema']));
    }
};
