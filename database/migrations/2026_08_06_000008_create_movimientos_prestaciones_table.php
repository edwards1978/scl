<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_prestaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrono_id')->constrained('patronos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();

            $table->string('tipo'); // anticipo | prestamo
            $table->date('fecha');
            $table->string('concepto')->nullable();
            $table->string('motivo')->nullable(); // vivienda | educacion | salud (solo anticipo)

            $table->decimal('monto', 15, 2);
            $table->string('moneda'); // moneda entregada (BS | USD)
            $table->decimal('tasa_cambio', 15, 4)->nullable();

            // Datos de préstamo
            $table->unsignedInteger('plazo_meses')->nullable();
            $table->decimal('interes_anual', 8, 4)->nullable();
            $table->decimal('cuota', 15, 2)->nullable();
            $table->decimal('saldo', 15, 2)->nullable();

            $table->string('estatus')->default('activo'); // activo | pagado | anulado
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        Schema::create('movimiento_prestaciones_intereses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movimiento_prestacion_id');
            $table->date('fecha');
            $table->decimal('monto', 15, 2);
            $table->string('moneda');
            $table->decimal('tasa_cambio', 15, 4)->nullable();
            $table->string('concepto')->nullable();
            $table->timestamps();

            // El nombre de una FK es único por base de datos, no por tabla.
            // Se prefija para que el esquema de pruebas (test_) no choque con
            // la FK real de producción.
            $table->foreign(
                'movimiento_prestacion_id',
                DB::getTablePrefix().'mpi_movimiento_fk'
            )
                ->references('id')->on('movimientos_prestaciones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_prestaciones_intereses');
        Schema::dropIfExists('movimientos_prestaciones');
    }
};
