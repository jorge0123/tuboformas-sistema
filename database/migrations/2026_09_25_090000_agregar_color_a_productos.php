<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Variantes del mismo producto (Copla 3/4" gris / naranja): mismo nombre, distinto color y código.
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('color', 40)->nullable()->after('medida');
        });
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('color'));
    }
};
