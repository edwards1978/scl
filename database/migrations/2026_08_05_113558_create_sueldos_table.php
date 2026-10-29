<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sueldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->string('periodo', 7)->comment('YYYY-MM');
            $table->date('fecha_cierre')->comment('último día del mes del periodo');
            $table->enum('moneda_sueldo', ['USD', 'BS'])->comment('moneda en la que se definió el sueldo');
            $table->decimal('monto_usd', 14, 2)->nullable()->comment('equivalente en USD');
            $table->decimal('monto_bs', 14, 2)->nullable()->comment('equivalente en Bs');
            $table->decimal('tasa_bcv', 14, 6)->nullable();
            $table->string('origen', 20)->default('ficha')->comment('ficha | cambio');
            $table->timestamps();

            $table->unique(['empleado_id', 'periodo']);
            $table->index(['empleado_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sueldos');
    }
};
