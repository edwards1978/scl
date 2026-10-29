<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PapeleraService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Purga definitivamente los registros que superaron la retención de la papelera.
 *
 * Sin `--aplicar` solo muestra lo que haría. Los registros con dependencias que
 * se borrarían en cascada quedan bloqueados y se informan para revisión manual.
 */
class PurgarPapelera extends Command
{
    protected $signature = 'papelera:purgar
                            {--dias= : Días de retención; por defecto el configurado}
                            {--tipo= : Purga solo un tipo de registro}
                            {--aplicar : Elimina los registros; sin esta opción solo simula la operación}';

    protected $description = 'Purga definitivamente los registros que superaron la retención de la papelera';

    public function handle(PapeleraService $papelera): int
    {
        $dias = $this->option('dias') !== null
            ? (int) $this->option('dias')
            : $papelera->retencionDias();

        $tipo = $this->option('tipo');
        $tipo = is_string($tipo) && $tipo !== '' ? $tipo : null;

        if ($tipo !== null) {
            try {
                $papelera->config($tipo);
            } catch (InvalidArgumentException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if ($dias < 1) {
            $this->warn('La retención de la papelera está desactivada (0 días). No se purgó nada.');

            return self::SUCCESS;
        }

        $aplicar = (bool) $this->option('aplicar');
        $resumen = $papelera->purgarExpirados($dias, $tipo, ! $aplicar);

        if ($resumen === []) {
            $this->info('No hay registros que superen la retención.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tipo', 'Registros', 'Bloqueados'],
            collect($resumen)
                ->map(fn (array $fila): array => [$fila['label'], $fila['purgados'], count($fila['bloqueados'])])
                ->values()
                ->all(),
        );

        foreach ($resumen as $fila) {
            foreach ($fila['bloqueados'] as $bloqueado) {
                $this->line(" - {$fila['label']} #{$bloqueado['id']}: {$bloqueado['motivo']}");
            }
        }

        if ($aplicar) {
            $this->info('Purga completada.');
        } else {
            $this->warn('Simulación: no se eliminó ningún registro. Use --aplicar para ejecutar la purga.');
        }

        return self::SUCCESS;
    }
}
