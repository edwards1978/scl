<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidacion_conceptos', function (Blueprint $table) {
            $table->string('tipo_salario')->nullable()->after('salario_diario');
        });
    }

    public function down(): void
    {
        Schema::table('liquidacion_conceptos', function (Blueprint $table) {
            $table->dropColumn('tipo_salario');
        });
    }
};