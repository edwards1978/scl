<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestaciones_sociales', function (Blueprint $table) {
            $table->boolean('es_proyeccion')->default(false)->after('saldo_final');
        });
    }

    public function down(): void
    {
        Schema::table('prestaciones_sociales', function (Blueprint $table) {
            $table->dropColumn('es_proyeccion');
        });
    }
};
