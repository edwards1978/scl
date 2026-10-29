<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_interes_prestaciones', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7)->unique(); // YYYY-MM
            $table->decimal('tasa_interes', 8, 4);
            $table->string('fuente')->default('manual'); // excel | manual
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_interes_prestaciones');
    }
};
