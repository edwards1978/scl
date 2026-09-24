<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla global de feriados fijos (día/mes, sin año) editables por el usuario.
     * Los feriados variables (Carnaval y Semana Santa) se calculan y no se
     * guardan aquí; se materializan en `feriados` por el servicio.
     */
    public function up(): void
    {
        Schema::create('feriados_fijos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('mes');
            $table->unsignedTinyInteger('dia');
            $table->string('descripcion', 150);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['mes', 'dia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados_fijos');
    }
};