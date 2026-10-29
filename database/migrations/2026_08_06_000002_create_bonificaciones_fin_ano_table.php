<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonificaciones_fin_ano', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrono_id')->constrained('patronos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->decimal('meses_laborados', 5, 2)->default(0);
            $table->unsignedSmallInteger('dias_base')->default(30);
            $table->decimal('dias_a_pagar', 5, 2)->default(0);
            $table->enum('tipo_base', ['ultimo', 'promedio'])->default('ultimo');
            $table->decimal('salario_mensual_usd', 14, 2)->nullable();
            $table->decimal('salario_mensual_bs', 14, 2)->nullable();
            $table->decimal('bono_usd', 14, 2)->nullable();
            $table->decimal('bono_bs', 14, 2)->nullable();
            $table->decimal('tasa_cambio', 10, 4)->default(0);
            $table->enum('estatus', ['calculado', 'aprobado'])->default('calculado');
            $table->date('fecha_aprobacion')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->json('asignaciones')->nullable();
            $table->json('deducciones')->nullable();
            $table->decimal('total_asignaciones', 14, 2)->default(0);
            $table->decimal('total_deducciones', 14, 2)->default(0);
            $table->decimal('neto', 14, 2)->default(0);
            $table->string('moneda', 3)->default('BS');
            $table->timestamps();

            $table->unique(['empleado_id', 'anio'], 'bonificacion_empleado_anio_unico');
            $table->index(['patrono_id', 'anio'], 'bonificacion_patrono_anio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonificaciones_fin_ano');
    }
};
