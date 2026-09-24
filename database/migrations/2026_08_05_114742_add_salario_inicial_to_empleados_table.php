<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->decimal('salario_inicial', 14, 2)->nullable()->after('salario_base')
                ->comment('sueldo inicial antes de cualquier cambio salarial');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('salario_inicial');
        });
    }
};