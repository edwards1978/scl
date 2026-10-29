<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla global de salarios mínimos legales (Venezuela), editable por el
     * usuario. `mensual_bs`/`diario_bs` se almacenan en Bs reexpresados para
     * los cálculos; el valor histórico original va en la columna `nota`.
     */
    public function up(): void
    {
        Schema::create('salarios_minimos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_vigencia');
            $table->string('gaceta_oficial', 50);
            $table->decimal('mensual_bs', 20, 8);
            $table->decimal('diario_bs', 20, 8);
            $table->string('nota', 255)->nullable();
            $table->timestamps();

            $table->index('fecha_vigencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salarios_minimos');
    }
};
