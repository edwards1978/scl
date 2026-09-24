<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->date('aniversario')->nullable()->after('dias');
            $table->unsignedInteger('anio_servicio')->nullable()->after('aniversario');
            $table->enum('estado', ['Pendiente', 'Disfrutado', 'Cancelado'])
                ->default('Pendiente')->after('anio_servicio');
            $table->decimal('monto_pagado', 14, 2)->default(0)->after('observaciones');
            $table->date('fecha_inicio_disfrute')->nullable()->after('monto_pagado');
            $table->date('fecha_retiro')->nullable()->after('fecha_inicio_disfrute');
        });
    }

    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropColumn([
                'aniversario', 'anio_servicio', 'estado', 'monto_pagado',
                'fecha_inicio_disfrute', 'fecha_retiro',
            ]);
        });
    }
};
