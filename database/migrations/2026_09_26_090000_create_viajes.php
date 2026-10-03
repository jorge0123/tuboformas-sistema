<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Viajes de entrega: un camión con su piloto lleva varios pedidos, en orden de paradas.
 * Ver docs/DISENO.md §2.8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viajes', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('vehiculo_id')->constrained('maquinas');
            $table->foreignId('piloto_id')->constrained('users');
            $table->string('estado', 20)->default('en_ruta')->index(); // en_ruta, terminado
            $table->timestamp('salida_at');
            $table->timestamp('regreso_at')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('user_id')->constrained('users'); // quien lo despachó
            $table->timestamps();
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('viaje_id')->nullable()->after('piloto_id')->constrained('viajes')->nullOnDelete();
            $table->unsignedSmallInteger('orden_parada')->nullable()->after('viaje_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('viaje_id');
            $table->dropColumn('orden_parada');
        });
        Schema::dropIfExists('viajes');
    }
};
