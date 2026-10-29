<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalculoLiquidacionLiteralC;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Cálculo independiente del Art. 142 literal c de la LOTTT.
 *
 * No consulta ni modifica patronos, empleados, prestaciones, nómina ni
 * movimientos. Recibe los datos escritos en el formulario y devuelve los
 * conceptos para guardar y generar el recibo.
 */
class CalculoLiquidacionLiteralCService
{
    public const DIAS_ANIO = 360;

    public const TOPE_VACACIONES = 30;

    public function __construct(
        private TasasBcvService $tasas = new TasasBcvService,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function calcular(array $datos): array
    {
        $ingreso = Carbon::parse((string) $datos['fecha_ingreso'])->startOfDay();
        $egreso = Carbon::parse((string) $datos['fecha_egreso'])->startOfDay();

        if ($egreso->lt($ingreso)) {
            throw ValidationException::withMessages([
                'fecha_egreso' => 'La fecha de egreso no puede ser anterior a la fecha de ingreso.',
            ]);
        }

        $salario = (float) $datos['salario'];
        $tipoSalario = (string) $datos['tipo_salario'];
        $normal = $this->salarioNormalDiario($salario, $tipoSalario);
        $diasUtilidades = (int) $datos['dias_utilidades'];

        $antiguedadC = $this->antiguedadLiteralC($ingreso, $egreso);
        $aniosServicio = (int) $ingreso->diffInYears($egreso);
        $diasVacacionesAnio = $this->diasVacacionesPorAnio($aniosServicio);

        if ((int) $datos['anios_vacaciones_vencidas'] > $aniosServicio) {
            throw ValidationException::withMessages([
                'anios_vacaciones_vencidas' => 'Los años de vacaciones vencidas no pueden superar los años efectivos de servicio.',
            ]);
        }

        if ((int) $datos['anios_utilidades_vencidas'] > $aniosServicio) {
            throw ValidationException::withMessages([
                'anios_utilidades_vencidas' => 'Los años de utilidades vencidas no pueden superar los años efectivos de servicio.',
            ]);
        }

        $integral = $this->salarioIntegralDiario($normal, $diasUtilidades, $diasVacacionesAnio);

        $diasC = $antiguedadC['anios'] * 30 + ($antiguedadC['meses'] >= 6 ? 30 : 0);

        $inicioUltimoAnio = $ingreso->copy()->addYears($aniosServicio);
        $mesesVacaciones = min(12, $this->mesesCompletos($inicioUltimoAnio, $egreso));
        $diasVacacionesFraccionadas = round($diasVacacionesAnio * $mesesVacaciones / 12, 2);

        $inicioUtilidades = Carbon::create($egreso->year, 1, 1)->startOfDay();
        if ($ingreso->gt($inicioUtilidades)) {
            $inicioUtilidades = $ingreso->copy();
        }
        $mesesUtilidades = min(12, $this->mesesCompletos($inicioUtilidades, $egreso));
        $diasUtilidadesFraccionadas = round($diasUtilidades * $mesesUtilidades / 12, 2);

        $aniosVacacionesVencidas = (int) $datos['anios_vacaciones_vencidas'];
        $aniosUtilidadesVencidas = (int) $datos['anios_utilidades_vencidas'];

        $diasVacacionesVencidas = $this->diasVacacionesVencidas($aniosVacacionesVencidas);
        $diasUtilidadesVencidas = $aniosUtilidadesVencidas * $diasUtilidades;

        $asignaciones = [
            $this->fila('Prestaciones sociales (Art. 142.c)', 'Art. 142 literal c LOTTT', $diasC, $integral, $diasC * $integral, 'integral'),
            $this->fila('Vacaciones fraccionadas', 'Art. 196 LOTTT', $diasVacacionesFraccionadas, $normal, $diasVacacionesFraccionadas * $normal, 'normal'),
            $this->fila('Bono vacacional fraccionado', 'Art. 192 LOTTT', $diasVacacionesFraccionadas, $normal, $diasVacacionesFraccionadas * $normal, 'normal'),
            $this->fila('Utilidades fraccionadas', 'Art. 131 LOTTT', $diasUtilidadesFraccionadas, $normal, $diasUtilidadesFraccionadas * $normal, 'normal'),
        ];

        if ($diasVacacionesVencidas > 0) {
            $asignaciones[] = $this->fila('Vacaciones vencidas', 'Art. 190 LOTTT', $diasVacacionesVencidas, $normal, $diasVacacionesVencidas * $normal, 'normal');
            $asignaciones[] = $this->fila('Bono vacacional vencido', 'Art. 192 LOTTT', $diasVacacionesVencidas, $normal, $diasVacacionesVencidas * $normal, 'normal');
        }

        if ($diasUtilidadesVencidas > 0) {
            $asignaciones[] = $this->fila('Utilidades vencidas', 'Art. 131 LOTTT', $diasUtilidadesVencidas, $normal, $diasUtilidadesVencidas * $normal, 'normal');
        }

        $deducciones = [
            $this->fila('Anticipo de prestaciones', 'Art. 144 LOTTT', null, null, (float) ($datos['anticipo'] ?? 0), null, 'deduccion'),
            $this->fila('Intereses pagados', 'Art. 143 LOTTT', null, null, (float) ($datos['intereses_pagados'] ?? 0), null, 'deduccion'),
            $this->fila('Saldo de préstamos personales', 'Acuerdo de préstamo', null, null, (float) ($datos['prestamo_saldo'] ?? 0), null, 'deduccion'),
        ];

        $totalAsignaciones = round(array_sum(array_column($asignaciones, 'importe')), 2);
        $totalDeducciones = round(array_sum(array_column($deducciones, 'importe')), 2);
        $neto = round($totalAsignaciones - $totalDeducciones, 2);

        $tasa = $this->tasas->tasaEn($egreso);
        $equivalencia = null;
        if ($tasa !== null && $tasa > 0) {
            $equivalencia = (string) $datos['moneda'] === CalculoLiquidacionLiteralC::MONEDA_USD
                ? ['moneda' => 'BS', 'monto' => round($neto * $tasa, 2)]
                : ['moneda' => 'USD', 'monto' => round($neto / $tasa, 2)];
        }

        return [
            'anios_servicio' => $antiguedadC['anios'],
            'meses_fraccion' => $antiguedadC['meses'],
            'dias_prestaciones' => $diasC,
            'meses_vacaciones_fraccionadas' => $mesesVacaciones,
            'meses_utilidades_fraccionadas' => $mesesUtilidades,
            'salario_diario_normal' => round($normal, 2),
            'salario_diario_integral' => round($integral, 2),
            'tasa_bcv' => $tasa,
            'asignaciones' => $asignaciones,
            'deducciones' => $deducciones,
            'total_asignaciones' => $totalAsignaciones,
            'total_deducciones' => $totalDeducciones,
            'neto' => $neto,
            'equivalencia' => $equivalencia,
            'conceptos' => array_merge($asignaciones, $deducciones),
        ];
    }

    public function salarioNormalDiario(float $salario, string $tipo): float
    {
        return match ($tipo) {
            CalculoLiquidacionLiteralC::TIPO_SEMANAL => $salario / 7,
            CalculoLiquidacionLiteralC::TIPO_QUINCENAL => $salario / 15,
            CalculoLiquidacionLiteralC::TIPO_DIARIO => $salario,
            default => $salario / 30,
        };
    }

    public function salarioIntegralDiario(float $normal, int $diasUtilidades, int $diasVacaciones): float
    {
        $alicuotaUtilidades = $normal * $diasUtilidades / self::DIAS_ANIO;
        $alicuotaBono = $normal * $diasVacaciones / self::DIAS_ANIO;

        return $normal + $alicuotaUtilidades + $alicuotaBono;
    }

    public function diasVacacionesPorAnio(int $aniosServicio): int
    {
        return min(15 + max(0, $aniosServicio), self::TOPE_VACACIONES);
    }

    public function diasVacacionesVencidas(int $aniosVencidos): int
    {
        $dias = 0;

        for ($i = 0; $i < $aniosVencidos; $i++) {
            $dias += min(15 + $i, self::TOPE_VACACIONES);
        }

        return $dias;
    }

    /**
     * Meses completos estrictos: no se prorratean días.
     */
    public function mesesCompletos(Carbon $inicio, Carbon $fin): int
    {
        if ($fin->lte($inicio)) {
            return 0;
        }

        return max(0, (int) $inicio->diffInMonths($fin));
    }

    /**
     * Antigüedad del literal c: meses completos con regla de 15 días o más
     * para el mes en curso.
     *
     * @return array{anios:int, meses:int}
     */
    public function antiguedadLiteralC(Carbon $ingreso, Carbon $egreso): array
    {
        $anios = (int) $ingreso->diffInYears($egreso);
        $resto = $ingreso->copy()->addYears($anios);
        $meses = (int) $resto->diffInMonths($egreso);
        $fechaMes = $resto->copy()->addMonthsNoOverflow($meses);

        if ($fechaMes->diffInDays($egreso) >= 15) {
            $meses++;
        }

        if ($meses >= 12) {
            $anios++;
            $meses = 0;
        }

        return ['anios' => $anios, 'meses' => $meses];
    }

    /**
     * @return array{concepto:string, base_legal:string, dias:?float, salario_diario:?float, importe:float, tipo_salario:?string, tipo:string}
     */
    private function fila(
        string $concepto,
        string $baseLegal,
        float|int|null $dias,
        float|int|null $salarioDiario,
        float|int $importe,
        ?string $tipoSalario,
        string $tipo = 'asignacion',
    ): array {
        return [
            'concepto' => $concepto,
            'base_legal' => $baseLegal,
            'dias' => $dias !== null ? round((float) $dias, 2) : null,
            'salario_diario' => $salarioDiario !== null ? round((float) $salarioDiario, 2) : null,
            'importe' => round((float) $importe, 2),
            'tipo_salario' => $tipoSalario,
            'tipo' => $tipo,
        ];
    }
}
