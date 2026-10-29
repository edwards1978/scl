<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas que registran quién las creó y quién las modificó por última vez.
     *
     * @var list<string>
     */
    private array $tablasConUsuarios = [
        'patronos',
        'empleados',
        'cambios_salarios',
        'pagos_interes_prestaciones',
        'feriados_fijos',
        'tasas_bcv',
        'tasas_interes_prestaciones',
        'salarios_minimos',
        'liquidaciones',
        'vacaciones',
        'bonificaciones_fin_ano',
        'movimientos_prestaciones',
    ];

    /**
     * Tablas que además guardan el usuario y la fecha de aprobación o anulación.
     *
     * @var array<string, array{aprobacion: bool, anulacion: bool}>
     */
    private array $tablasConSellos = [
        'liquidaciones' => ['aprobacion' => true, 'anulacion' => true],
        'vacaciones' => ['aprobacion' => true, 'anulacion' => true],
        'bonificaciones_fin_ano' => ['aprobacion' => true, 'anulacion' => false],
        'movimientos_prestaciones' => ['aprobacion' => false, 'anulacion' => true],
    ];

    public function up(): void
    {
        foreach ($this->tablasConUsuarios as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        foreach ($this->tablasConSellos as $tabla => $sellos) {
            Schema::table($tabla, function (Blueprint $table) use ($sellos): void {
                if ($sellos['aprobacion']) {
                    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('approved_at')->nullable();
                }

                if ($sellos['anulacion']) {
                    $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('annulled_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablasConSellos as $tabla => $sellos) {
            Schema::table($tabla, function (Blueprint $table) use ($sellos): void {
                if ($sellos['aprobacion']) {
                    $table->dropConstrainedForeignId('approved_by');
                    $table->dropColumn('approved_at');
                }

                if ($sellos['anulacion']) {
                    $table->dropConstrainedForeignId('annulled_by');
                    $table->dropColumn('annulled_at');
                }
            });
        }

        foreach ($this->tablasConUsuarios as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('created_by');
                $table->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
