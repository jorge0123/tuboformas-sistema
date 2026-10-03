<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración editable desde el sistema (correo SMTP) y reportes que se envían solos
 * a una hora (docs/DISENO.md §2.11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        Schema::create('envios_programados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->time('hora');
            $table->json('dias');                       // 1 = lunes … 7 = domingo
            $table->string('periodo', 10)->default('hoy'); // hoy, ayer, semana
            $table->json('reportes');                   // claves de App\Support\ReporteProgramado::REPORTES
            $table->json('usuarios')->nullable();       // ids de usuarios que lo reciben
            $table->text('correos')->nullable();        // correos extra (gerencia, externos), separados por coma
            $table->boolean('por_correo')->default(true);
            $table->boolean('en_campana')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->timestamps();
        });

        // Lo que se envió, para verlo desde la campana o el historial.
        Schema::create('reportes_enviados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_programado_id')->nullable()->constrained('envios_programados')->nullOnDelete();
            $table->string('titulo', 150);
            $table->json('destinatarios')->nullable();  // ids de usuarios (para dar acceso al verlo)
            $table->unsignedSmallInteger('correos_enviados')->default(0);
            $table->string('error')->nullable();
            $table->longText('html');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_enviados');
        Schema::dropIfExists('envios_programados');
        Schema::dropIfExists('configuraciones');
    }
};
