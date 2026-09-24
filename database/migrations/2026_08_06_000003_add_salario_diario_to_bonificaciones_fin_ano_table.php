<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonificaciones_fin_ano', function (Blueprint $table) {
            $table->decimal('salario_diario_usd', 14, 2)->nullable()->after('salario_mensual_bs');
            $table->decimal('salario_diario_bs', 14, 2)->nullable()->after('salario_diario_usd');
        });
    }

    public function down(): void
    {
        Schema::table('bonificaciones_fin_ano', function (Blueprint $table) {
            $table->dropColumn(['salario_diario_usd', 'salario_diario_bs']);
        });
    }
};