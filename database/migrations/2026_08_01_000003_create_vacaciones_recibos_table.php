<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones_recibos');
    }
};
