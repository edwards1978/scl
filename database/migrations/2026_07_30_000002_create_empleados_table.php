<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrono_id')->constrained('patronos')->onDelete('cascade');
            $table->string('nombres', 255);
            $table->string('apellidos', 255);
            $table->string('cedula', 15);
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['M', 'F'])->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->date('fecha_ingreso');
            $table->string('cargo', 255)->nullable();
            $table->string('departamento', 255)->nullable();
            $table->enum('tipo_contrato', ['Determinado', 'Indeterminado', 'Obra']);
            $table->enum('tipo_salario', ['Fijo', 'Variable', 'Mixto']);
            $table->string('jornada_laboral', 100)->nullable();
            $table->decimal('salario_base', 14, 2)->default(0);
            $table->enum('estatus', ['Activo', 'Inactivo', 'Suspendido'])->default('Activo');
            $table->timestamps();

            $table->unique(['patrono_id', 'cedula']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
