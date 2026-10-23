<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestaciones_sociales', function (Blueprint $table) {
            $table->decimal('capital_inicial', 15, 2)->nullable()->after('saldo_inicial');
            $table->decimal('capital_final', 15, 2)->nullable()->after('capital_inicial');
            $table->decimal('interes_pendiente_inicial', 15, 2)->nullable()->after('capital_final');
            $table->decimal('interes_pendiente_final', 15, 2)->nullable()->after('interes_pendiente_inicial');
            $table->decimal('interes_capitalizado', 15, 2)->nullable()->after('interes_mes');
            $table->decimal('interes_pagado', 15, 2)->nullable()->after('interes_capitalizado');
        });
    }

    public function down(): void
    {
        Schema::table('prestaciones_sociales', function (Blueprint $table) {
            $table->dropColumn([
                'capital_inicial',
                'capital_final',
                'interes_pendiente_inicial',
                'interes_pendiente_final',
                'interes_capitalizado',
                'interes_pagado',
            ]);
        });
    }
};
