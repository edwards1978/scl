<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Salario integral diario según LOTTT.
 *
 * El salario integral = salario normal diario + la incidencia (alícuota)
 * diaria de las utilidades y la del bono vacacional:
 *
 *   salario_diario_normal   = salario_mensual / 30
 *   alicuota_utilidades     = (salario_diario_normal × dias_utilidades) / 360
 *   alicuota_bono_vacacional= (salario_diario_normal × dias_bono_vacacional) / 360
 *   salario_diario_integral = salario_diario_normal + alicuota_utilidades
 *                             + alicuota_bono_vacacional
 *
 * Base del cálculo de prestaciones sociales (Art. 142 LOTTT) e indemnización
 * por terminación (Art. 92 LOTTT). Reutilizable por otros módulos.
 */
class SalarioIntegralService
{
    public const DIAS_MES = 30;
    public const DIAS_ANNO_LABORAL = 360;

    public const BONO_BASE = 15;
    public const BONO_MAX = 30;

    public function __construct(
        private SueldosService $sueldos = new SueldosService,
        private TasasBcvService $tasas = new TasasBcvService,
    ) {
    }

    /**
     * Años de servicio completos a una fecha (misma lógica que vacaciones).
     */
    public function aniosServicio(Empleado $empleado, ?Carbon $fecha = null): int
    {
        $fecha = $fecha ?: Carbon::now();
        $ingreso = Carbon::instance($empleado->fecha_ingreso);

        return (int) $ingreso->diffInYears($fecha);
    }

    /**
     * Días de vacaciones y bono vacacional para un año de servicio dado,
     * incluyendo los días adicionales (15 + 1 por año adicional, tope 30).
     *
     * @return array{vacaciones:int, bono:int}
     */
    public function diasPorAnioServicio(int $anioServicio): array
    {
        $adicionales = min(max($anioServicio - 1, 0), self::BONO_MAX - self::BONO_BASE);

        return [
            'vacaciones' => self::BONO_BASE + $adicionales,
            'bono'       => self::BONO_BASE + $adicionales,
        ];
    }

    /**
     * Días de vacaciones: 15 + 1 por año en curso, tope 30 (Art. 190 LOTTT).
     * Usa el año de servicio en curso (completados + 1): el 2º año ya tiene
     * 16 días, el 3º 17, y así sucesivamente.
     */
    public function diasVacacionales(Empleado $empleado, ?Carbon $fecha = null): int
    {
        return $this->diasPorAnioServicio($this->aniosServicio($empleado, $fecha) + 1)['vacaciones'];
    }

    /**
     * Días de bono vacacional: 15 + 1 por año en curso, tope 30 (Art. 192 LOTTT).
     * Usa el año de servicio en curso (completados + 1): el 2º año ya tiene
     * 16 días, el 3º 17, y así sucesivamente.
     */
    public function diasBonoVacacional(Empleado $empleado, ?Carbon $fecha = null): int
    {
        return $this->diasPorAnioServicio($this->aniosServicio($empleado, $fecha) + 1)['bono'];
    }

    /**
     * Días de utilidades a usar en la alícuota: el configurable del patrono
     * (mínimo legal de referencia 15; se respeta el valor del patrono).
     */
    public function diasUtilidades(Empleado $empleado): int
    {
        $patrono = $empleado->patrono;

        return max((int) ($patrono->dias_bonificacion ?? 0), 0);
    }

    /**
     * Salario mensual vigente del empleado para una fecha, en su moneda, junto
     * a la tasa de referencia del día.
     *
     * @return array{mensual:float, moneda:string, tasa:?float}
     */
    public function salarioMensualVigente(Empleado $empleado, ?Carbon $fecha = null): array
    {
        $fecha = $fecha ?: Carbon::now();
        $vigente = $this->sueldos->sueldoVigente($empleado, $fecha);
        $tasa = $this->tasas->tasaEn($fecha);

        return [
            'mensual' => (float) $vigente['monto'],
            'moneda'  => (string) $vigente['moneda'],
            'tasa'    => $tasa,
        ];
    }

    /**
     * Detalle completo del salario integral diario del empleado a una fecha,
     * expresado en su moneda. Opcionalmente devuelve equivalencias en la moneda
     * contraria usando la tasa del día.
     *
     * @return array{salario_mensual:float, moneda:string, tasa:?float,
     *               salario_diario_normal:float, dias_utilidades:int,
     *               alicuota_utilidades:float, dias_bono_vacacional:int,
     *               alicuota_bono_vacacional:float, salario_diario_integral:float,
     *               mensual_integral:float}
     */
    public function diario(Empleado $empleado, ?Carbon $fecha = null): array
    {
        $fecha = $fecha ?: Carbon::now();
        $base = $this->salarioMensualVigente($empleado, $fecha);
        $mes = $base['mensual'];

        $salarioDiarioNormal = $mes / self::DIAS_MES;

        $diasUtil = $this->diasUtilidades($empleado);
        $diasBono = $this->diasBonoVacacional($empleado, $fecha);

        $alicuotaUtil = ($salarioDiarioNormal * $diasUtil) / self::DIAS_ANNO_LABORAL;
        $alicuotaBono = ($salarioDiarioNormal * $diasBono) / self::DIAS_ANNO_LABORAL;

        $integralDiario = $salarioDiarioNormal + $alicuotaUtil + $alicuotaBono;

        return [
            'salario_mensual'          => $mes,
            'moneda'                   => $base['moneda'],
            'tasa'                     => $base['tasa'],
            'salario_diario_normal'    => round($salarioDiarioNormal, 2),
            'dias_utilidades'          => $diasUtil,
            'alicuota_utilidades'      => round($alicuotaUtil, 2),
            'dias_bono_vacacional'     => $diasBono,
            'alicuota_bono_vacacional' => round($alicuotaBono, 2),
            'salario_diario_integral'  => round($integralDiario, 2),
            'mensual_integral'         => round($integralDiario * self::DIAS_MES, 2),
        ];
    }
}