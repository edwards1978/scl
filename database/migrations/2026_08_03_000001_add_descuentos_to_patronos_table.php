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
            $table->boolean('descuenta_ivss')->default(false)->after('estado');
            $table->boolean('descuenta_paro_fijo')->default(false)->after('descuenta_ivss');
            $table->boolean('descuenta_faov')->default(false)->after('descuenta_paro_fijo');
        });
    }

    public function down(): void
    {
        Schema::table('patronos', function (Blueprint $table) {
            $table->dropColumn(['descuenta_ivss', 'descuenta_paro_fijo', 'descuenta_faov']);
        });
    }
};
