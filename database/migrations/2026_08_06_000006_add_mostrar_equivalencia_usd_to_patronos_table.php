<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->boolean('mostrar_equivalencia_usd')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->dropColumn('mostrar_equivalencia_usd');
        });
    }
};
