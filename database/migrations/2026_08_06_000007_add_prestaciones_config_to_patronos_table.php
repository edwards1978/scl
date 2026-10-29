<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->string('deposito_tipo')->default('contabilidad')->after('dias_bonificacion');
            $table->unsignedSmallInteger('anticipos_max_anio')->default(2)->after('deposito_tipo');
            $table->unsignedTinyInteger('anticipos_pct_contabilidad')->default(100)->after('anticipos_max_anio');
            $table->unsignedTinyInteger('anticipos_pct_fideicomiso')->default(75)->after('anticipos_pct_contabilidad');
            $table->unsignedTinyInteger('anticipos_pct_fondo')->default(75)->after('anticipos_pct_fideicomiso');
        });
    }

    public function down(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->dropColumn([
                'deposito_tipo',
                'anticipos_max_anio',
                'anticipos_pct_contabilidad',
                'anticipos_pct_fideicomiso',
                'anticipos_pct_fondo',
            ]);
        });
    }
};
