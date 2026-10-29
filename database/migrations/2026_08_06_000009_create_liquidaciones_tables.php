<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrono_id')->constrained('patronos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();

            $table->date('fecha_liquidacion');
            $table->date('fecha_ingreso');
            $table->date('fecha_egreso');
            $table->string('motivo'); // renuncia | despido | retiro | mutuo_acuerdo | otro
            $table->text('observacion')->nullable();

            $table->unsignedSmallInteger('anios_servicio');
            $table->unsignedSmallInteger('meses_fraccion');
            $table->unsignedInteger('dias_prestaciones');
            $table->unsignedInteger('dias_indemnizacion')->default(0);

            $table->decimal('salario_integral_diario', 15, 2);
            $table->string('moneda');
            $table->decimal('tasa_cambio', 15, 4)->nullable();

            $table->decimal('total_asignaciones', 15, 2)->default(0);
            $table->decimal('total_deducciones', 15, 2)->default(0);
            $table->decimal('neto', 15, 2)->default(0);

            $table->string('estatus')->default('por_aprobar'); // por_aprobar | aprobada | anulada
            $table->timestamps();
        });

        Schema::create('liquidacion_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->string('tipo'); // asignacion | deduccion
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('concepto');
            $table->string('base_legal')->nullable();
            $table->decimal('dias', 10, 2)->nullable();
            $table->decimal('importe', 15, 2);
            $table->boolean('editable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_conceptos');
        Schema::dropIfExists('liquidaciones');
    }
};
