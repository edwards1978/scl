<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambios_salarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha_cambio')->comment('fecha en que entra en vigencia el cambio');
            $table->decimal('monto', 14, 2);
            $table->enum('moneda', ['USD', 'BS']);
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha_cambio']);
            $table->index('fecha_cambio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_salarios');
    }
};
