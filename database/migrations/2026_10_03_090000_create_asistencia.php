<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencia y tiempo laboral de las OT (docs/DISENO.md §2.10).
 * Quien tiene turno marca entrada y salida; el tiempo de una OT es la suma de sus tramos,
 * y un tramo solo corre mientras el técnico está marcado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->time('hora_entrada');
            $table->time('hora_salida');                       // menor que la entrada = termina al día siguiente
            $table->json('dias');                              // 1 = lunes … 7 = domingo
            $table->unsignedSmallInteger('tolerancia')->default(10); // minutos antes de contar como tarde
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('turno_id')->nullable()->after('especialidad_id')->constrained('turnos')->nullOnDelete();
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha')->index();                    // día en que entró (turnos de noche cruzan la medianoche)
            $table->timestamp('entrada_at');
            $table->timestamp('salida_at')->nullable();
            $table->boolean('salida_automatica')->default(false); // no marcó salida: se cerró al terminar su turno
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete(); // turno vigente ese día
            $table->string('notas')->nullable();
            $table->foreignId('corregida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'fecha']);
        });

        // Tiempo de trabajo efectivo en una OT, por persona.
        Schema::create('ot_tramos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('inicio_at');
            $table->timestamp('fin_at')->nullable();
            $table->string('cierre', 20)->nullable();          // pausa, salida, espera, completada, cancelada, otra_ot
            $table->timestamps();
            $table->index(['user_id', 'fin_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_tramos');
        Schema::dropIfExists('asistencias');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('turno_id'));
        Schema::dropIfExists('turnos');
    }
};
