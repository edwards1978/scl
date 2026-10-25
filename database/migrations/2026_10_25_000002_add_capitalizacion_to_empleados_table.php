<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->boolean('capitaliza_intereses')->default(false)->after('estatus');
            $table->date('capitalizacion_autorizada_desde')->nullable()->after('capitaliza_intereses');
            $table->string('capitalizacion_documento', 255)->nullable()->after('capitalizacion_autorizada_desde');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn([
                'capitaliza_intereses',
                'capitalizacion_autorizada_desde',
                'capitalizacion_documento',
            ]);
        });
    }
};
