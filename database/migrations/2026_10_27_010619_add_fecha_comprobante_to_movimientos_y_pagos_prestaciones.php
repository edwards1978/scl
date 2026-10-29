<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_prestaciones', function (Blueprint $table) {
            $table->date('fecha_comprobante')->nullable()->after('fecha');
        });

        Schema::table('pagos_interes_prestaciones', function (Blueprint $table) {
            $table->date('fecha_comprobante')->nullable()->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_prestaciones', function (Blueprint $table) {
            $table->dropColumn('fecha_comprobante');
        });

        Schema::table('pagos_interes_prestaciones', function (Blueprint $table) {
            $table->dropColumn('fecha_comprobante');
        });
    }
};
