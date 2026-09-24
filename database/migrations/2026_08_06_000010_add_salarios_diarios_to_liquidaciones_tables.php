<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->decimal('salario_diario_normal', 15, 2)->default(0)->after('salario_integral_diario');
        });

        Schema::table('liquidacion_conceptos', function (Blueprint $table) {
            $table->decimal('salario_diario', 15, 2)->nullable()->after('dias');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropColumn('salario_diario_normal');
        });

        Schema::table('liquidacion_conceptos', function (Blueprint $table) {
            $table->dropColumn('salario_diario');
        });
    }
};