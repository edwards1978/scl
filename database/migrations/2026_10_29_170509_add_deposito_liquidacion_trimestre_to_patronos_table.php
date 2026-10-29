<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->string('deposito_liquidacion_trimestre', 20)
                ->default('completo')
                ->after('deposito_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->dropColumn('deposito_liquidacion_trimestre');
        });
    }
};
