<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->unsignedSmallInteger('dias');
            $table->string('observaciones', 255)->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha_desde', 'fecha_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones');
    }
};
