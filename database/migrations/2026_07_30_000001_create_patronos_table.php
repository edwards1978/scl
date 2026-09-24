<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patronos', function (Blueprint $table) {
            $table->id();
            $table->string('rif', 20)->unique();
            $table->string('razon_social', 255);
            $table->string('nombre_comercial', 255)->nullable();
            $table->string('registro_mercantil', 255)->nullable();
            $table->text('direccion_fiscal')->nullable();
            $table->string('representante_legal', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 255)->nullable();
            $table->enum('estado', ['Activo', 'Inactivo'])->default('Activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patronos');
    }
};
