<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculos_liquidacion_literal_c', function (Blueprint $table) {
            $table->id();

            // Datos libres: este módulo no se vincula a patronos ni empleados.
            $table->string('patrono_nombre');
            $table->string('patrono_rif', 20)->nullable();
            $table->string('trabajador_nombre');
            $table->string('trabajador_cedula', 20)->nullable();
            $table->string('cargo')->nullable();

            $table->date('fecha_ingreso');
            $table->date('fecha_egreso');
            $table->string('tipo_salario', 20);
            $table->decimal('salario', 15, 2);
            $table->string('moneda', 3);
            $table->unsignedSmallInteger('dias_utilidades')->default(30);

            $table->unsignedSmallInteger('anios_vacaciones_vencidas')->default(0);
            $table->unsignedSmallInteger('anios_utilidades_vencidas')->default(0);

            $table->decimal('salario_diario_normal', 15, 2);
            $table->decimal('salario_diario_integral', 15, 2);
            $table->decimal('tasa_bcv', 15, 6)->nullable();

            $table->decimal('anticipo', 15, 2)->default(0);
            $table->decimal('intereses_pagados', 15, 2)->default(0);
            $table->decimal('prestamo_saldo', 15, 2)->default(0);

            $table->decimal('total_asignaciones', 15, 2);
            $table->decimal('total_deducciones', 15, 2);
            $table->decimal('neto', 15, 2);

            $table->json('conceptos')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('fecha_egreso');
            $table->index('trabajador_nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculos_liquidacion_literal_c');
    }
};
