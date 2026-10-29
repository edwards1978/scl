<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestaciones_sociales', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_deposito')->default(0)->after('deposito_trimestre');
            $table->unsignedTinyInteger('dias_adicionales_cont')->default(0)->after('dias_adicionales');
        });
    }

    public function down(): void
    {
        Schema::table('prestaciones_sociales', function (Blueprint $table) {
            $table->dropColumn(['dias_deposito', 'dias_adicionales_cont']);
        });
    }
};
