<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entregas con los recursos de la empresa: el vehículo sale de Máquinas (área Vehículos) y el
 * piloto es un usuario marcado como piloto. Los textos vehiculo/piloto quedan para transporte
 * externo (empresa y número de guía) y para pedidos anteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_piloto')->default(false)->after('especialidad_id');
        });
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('vehiculo_id')->nullable()->after('documento')->constrained('maquinas')->nullOnDelete();
            $table->foreignId('piloto_id')->nullable()->after('vehiculo_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehiculo_id');
            $table->dropConstrainedForeignId('piloto_id');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('es_piloto'));
    }
};
