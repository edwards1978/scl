<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias_descanso')->default(0)->after('dias_bono_adicionales');
            $table->unsignedTinyInteger('dias_feriados')->default(0)->after('dias_descanso');
        });
    }

    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropColumn(['dias_descanso', 'dias_feriados']);
        });
    }
};