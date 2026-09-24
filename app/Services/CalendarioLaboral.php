<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Lógica de calendario laboral venezolano.
 *
 * Un día hábil es todo día que no sea:
 * - día de descanso semanal del empleado (por defecto sábado 6 y domingo 7),
 * - feriado nacional (fijos + variables, materializados por DiasFeriadosService).
 */
class CalendarioLaboral
{
    /**
     * Días de descanso semanal por defecto (1=lunes ... 7=domingo).
     */
    public const DESCANSO_DEFAULT = [6, 7];

    public function __construct(
        private DiasFeriadosService $feriados = new DiasFeriadosService,
    ) {
    }

    /**
     * Días de descanso semanales de un empleado (lee su configuración).
     */
    public function diasDescansoEmpleado(Empleado $empleado): array
    {
        if ($empleado->dias_descanso) {
            return array_values(array_map(fn ($v) => (int) $v, explode(',', (string) $empleado->dias_descanso)));
        }

        return self::DESCANSO_DEFAULT;
    }

    /**
     * ¿El día es un feriado? (materializa el año on-demand).
     */
    public function esFeriado(CarbonInterface $fecha): bool
    {
        $anio = (int) $fecha->format('Y');

        return in_array($fecha->format('Y-m-d'), $this->feriados->asegurarAnio($anio), true);
    }

    /**
     * ¿Es un día hábil?
     */
    public function esDiaHabil(CarbonInterface $fecha, Empleado $empleado): bool
    {
        if (in_array((int) $fecha->format('N'), $this->diasDescansoEmpleado($empleado))) {
            return false;
        }

        return ! $this->esFeriado($fecha);
    }

    /**
     * Primer día hábil a partir de (y sin incluir) la fecha dada.
     */
    public function proximoDiaHabil(CarbonInterface $fecha, Empleado $empleado): Carbon
    {
        $d = Carbon::instance($fecha)->addDay();
        while (! $this->esDiaHabil($d, $empleado)) {
            $d->addDay();
        }

        return $d;
    }

    /**
     * Suma N días hábiles a partir de $inicio (incluye $inicio si es hábil) y
     * devuelve la fecha del N-ésimo día hábil.
     */
    public function sumarDiasHabiles(CarbonInterface $inicio, int $dias, Empleado $empleado): Carbon
    {
        $actual = Carbon::instance($inicio);
        $contados = 0;

        while ($contados < $dias) {
            if ($this->esDiaHabil($actual, $empleado)) {
                $contados++;
            }
            if ($contados < $dias) {
                $actual->addDay();
            }
        }

        return $actual;
    }

    /**
     * Lista de feriados entre dos fechas (inclusive).
     */
    public function feriadosEnPeriodo(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $coleccion = collect();
        $d = Carbon::instance($desde);
        $fin = Carbon::instance($hasta);

        while ($d <= $fin) {
            if ($this->esFeriado($d)) {
                $coleccion->push($d->copy());
            }
            $d->addDay();
        }

        return $coleccion
            ->sort()
            ->map(fn (Carbon $c) => $c->format('d/m/Y').' ('.\App\Models\Feriado::where('fecha', $c->format('Y-m-d'))->value('descripcion').')')
            ->values()
            ->all();
    }

    /**
     * Cuenta los días hábiles entre dos fechas (inclusive ambos extremos si son hábiles).
     */
    public function contarDiasHabiles(CarbonInterface $desde, CarbonInterface $hasta, Empleado $empleado): int
    {
        $conteo = 0;
        $d = Carbon::instance($desde);
        while ($d <= $hasta) {
            if ($this->esDiaHabil($d, $empleado)) {
                $conteo++;
            }
            $d->addDay();
        }

        return $conteo;
    }
}