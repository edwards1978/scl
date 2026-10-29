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
            $table->unsignedTinyInteger('dias_bonificacion')->default(30)->after('descuenta_faov');
        });
    }

    public function down(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->dropColumn('dias_bonificacion');
        });
    }
};
