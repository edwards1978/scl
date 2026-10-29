<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestaciones_sociales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrono_id')->constrained('patronos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();

            $table->string('periodo', 7); // YYYY-MM
            $table->date('fecha_cierre');
            $table->unsignedSmallInteger('anios_servicio')->default(0);
            $table->unsignedSmallInteger('trimestre')->default(0);

            $table->decimal('salario_integral_diario', 15, 2);
            $table->string('moneda');

            $table->decimal('tasa_interes', 8, 4)->nullable();

            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->decimal('deposito_trimestre', 15, 2)->default(0);
            $table->decimal('dias_adicionales', 15, 2)->default(0);
            $table->decimal('interes_mes', 15, 2)->default(0);
            $table->decimal('anticipos', 15, 2)->default(0);
            $table->decimal('saldo_final', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['empleado_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestaciones_sociales');
    }
};
