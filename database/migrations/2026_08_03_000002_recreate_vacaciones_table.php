<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recibos references vacaciones(vacacion_id); drop first to release the FK.
        Schema::dropIfExists('vacaciones_recibos');
        Schema::dropIfExists('vacaciones');

        Schema::create('vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrono_id')->constrained('patronos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha_calculo');
            $table->date('fecha_inicio')->comment('suministrada por el usuario');
            $table->date('fecha_inicio_real')->nullable();
            $table->date('fecha_fin_real')->nullable();
            $table->date('fecha_reintegro')->nullable();
            $table->unsignedTinyInteger('nro_vacacion')->comment('años de servicio del periodo');
            $table->date('periodo_desde')->comment('aniversario (N-1) — fecha de ingreso si N=1');
            $table->date('periodo_hasta')->comment('aniversario N');
            $table->unsignedTinyInteger('dias_vacaciones')->default(15);
            $table->unsignedTinyInteger('dias_adicionales')->default(0);
            $table->unsignedTinyInteger('dias_bono')->default(15);
            $table->unsignedTinyInteger('dias_bono_adicionales')->default(0);
            $table->json('feriados_descanso_en_periodo')->nullable();
            $table->decimal('salario_normal', 14, 2)->default(0);
            $table->string('moneda', 3);
            $table->enum('estatus_calculo', ['por_aprobar', 'aprobada', 'no_aprobada'])
                ->default('por_aprobar');
            $table->enum('estatus_vacacion', ['pendiente', 'pagada', 'disfrutada'])
                ->default('pendiente');
            $table->timestamps();

            $table->unique(['patrono_id', 'empleado_id', 'periodo_desde', 'periodo_hasta'], 'vacaciones_periodo_unico');
            $table->index(['estatus_calculo', 'estatus_vacacion'], 'vacaciones_estatus_idx');
        });

        Schema::create('vacaciones_recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacacion_id')->constrained('vacaciones')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha_emision');
            $table->date('fecha_inicio');
            $table->date('fecha_retiro');
            $table->json('asignaciones');
            $table->json('deducciones');
            $table->decimal('total_asignaciones', 14, 2)->default(0);
            $table->decimal('total_deducciones', 14, 2)->default(0);
            $table->decimal('neto', 14, 2)->default(0);
            $table->string('moneda', 3)->default('BS');
            $table->decimal('tipo_cambio', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones_recibos');
        Schema::dropIfExists('vacaciones');
    }
};
