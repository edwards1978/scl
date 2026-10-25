<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->decimal('garantia_ab', 15, 2)->nullable()->after('dias_prestaciones');
            $table->decimal('literal_c', 15, 2)->nullable()->after('garantia_ab');
            $table->decimal('prestaciones_mayor', 15, 2)->nullable()->after('literal_c');
            $table->string('prestaciones_origen', 20)->nullable()->after('prestaciones_mayor');
            $table->decimal('prestaciones_diferencia', 15, 2)->nullable()->after('prestaciones_origen');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropColumn([
                'garantia_ab',
                'literal_c',
                'prestaciones_mayor',
                'prestaciones_origen',
                'prestaciones_diferencia',
            ]);
        });
    }
};
