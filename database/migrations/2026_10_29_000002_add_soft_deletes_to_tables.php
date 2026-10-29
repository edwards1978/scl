<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado lógico de los documentos y maestros que se pueden restaurar desde
 * la papelera. Las tablas generadas (`sueldos`, `prestaciones_sociales`) y los
 * catálogos globales (tasas, feriados, salarios mínimos) siguen con borrado
 * físico.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tablas = [
        'empleados',
        'patronos',
        'users',
        'vacaciones',
        'vacaciones_recibos',
        'liquidaciones',
        'bonificaciones_fin_ano',
        'movimientos_prestaciones',
        'pagos_interes_prestaciones',
        'cambios_salarios',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            if (! Schema::hasColumn($tabla, 'deleted_at')) {
                Schema::table($tabla, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasColumn($tabla, 'deleted_at')) {
                Schema::table($tabla, function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
