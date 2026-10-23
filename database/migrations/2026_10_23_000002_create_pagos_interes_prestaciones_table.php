<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_interes_prestaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrono_id')->constrained('patronos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 15, 2);
            $table->string('moneda', 3);
            $table->decimal('tasa_cambio', 15, 4)->nullable();
            $table->string('comprobante', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'fecha'], 'pip_empleado_fecha_idx');
            $table->index(['patrono_id', 'fecha'], 'pip_patrono_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_interes_prestaciones');
    }
};
