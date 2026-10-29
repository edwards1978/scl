<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_bcv', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('tasa_usd', 14, 6)->comment('Bs por USD (venta BCV)');
            $table->string('fuente', 50)->default('manual')->comment('manual | excel');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_bcv');
    }
};
