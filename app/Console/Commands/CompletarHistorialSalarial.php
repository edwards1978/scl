<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Empleado;
use App\Services\SueldosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa el historial salarial de los trabajadores creados antes de que
 * existiera el registro automático de cambios.
 *
 * - Si `salario_inicial` está vacío, lo completa con el primer cambio salarial
 *   conocido o, en su defecto, con el salario base actual.
 * - Si el trabajador no tiene ningún cambio salarial, crea el de ingreso con
 *   la fecha de ingreso y ese monto.
 *
 * Sin `--aplicar` solo muestra lo que haría (simulación).
 */
class CompletarHistorialSalarial extends Command
{
    protected $signature = 'salarios:completar-historial
                            {--aplicar : Guarda los cambios; sin esta opción solo simula la operación}';

    protected $description = 'Completa salario_inicial y el cambio salarial de ingreso de trabajadores antiguos';

    public function handle(SueldosService $sueldos): int
    {
        $aplicar = (bool) $this->option('aplicar');

        $conInicial = 0;
        $conCambio = 0;
        $procesados = 0;

        $empleados = Empleado::query()->with('cambiosSalarios')->orderBy('id')->get();

        foreach ($empleados as $empleado) {
            $primerCambio = $empleado->cambiosSalarios->sortBy('fecha_cambio')->first();

            $inicial = $empleado->salario_inicial !== null
                ? (float) $empleado->salario_inicial
                : (float) ($primerCambio?->monto ?? $empleado->salario_base);

            if ($inicial <= 0) {
                continue;
            }

            $requiereInicial = $empleado->salario_inicial === null;
            $requiereCambio = $primerCambio === null;

            if (! $requiereInicial && ! $requiereCambio) {
                continue;
            }

            $procesados++;
            $conInicial += $requiereInicial ? 1 : 0;
            $conCambio += $requiereCambio ? 1 : 0;

            $this->line(sprintf(
                '%s %s (%s): inicial %.2f %s%s',
                $requiereInicial ? 'Completar' : 'Mantener',
                $empleado->nombre_completo,
                $empleado->cedula,
                $inicial,
                $empleado->moneda,
                $requiereCambio ? ' + cambio de ingreso '.$empleado->fecha_ingreso->toDateString() : '',
            ));

            if (! $aplicar) {
                continue;
            }

            DB::transaction(function () use ($empleado, $sueldos, $inicial, $requiereInicial, $requiereCambio): void {
                if ($requiereInicial) {
                    $empleado->update(['salario_inicial' => $inicial]);
                }

                if ($requiereCambio) {
                    $sueldos->registrarCambio(
                        $empleado,
                        $empleado->fecha_ingreso,
                        $inicial,
                        (string) $empleado->moneda,
                    );
                }
            });
        }

        if (! $aplicar) {
            $this->warn("Simulación: {$procesados} trabajador(es) por completar ({$conInicial} sin salario inicial, {$conCambio} sin cambios). Use --aplicar para guardar.");

            return self::SUCCESS;
        }

        $this->info("Historial salarial completado: {$procesados} trabajador(es) ({$conInicial} salario inicial, {$conCambio} cambio de ingreso).");

        return self::SUCCESS;
    }
}
