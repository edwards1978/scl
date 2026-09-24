<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use App\Models\MovimientoPrestaciones;
use App\Models\PrestacionSocial;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Servicio de prestaciones sociales (garantía de prestaciones, Art. 142 LOTTT)
 * y sus intereses (Art. 143 LOTTT).
 *
 * Reglas:
 * - Depósito trimestral: 15 días de salario integral por cada trimestre cumplido
 *   desde la fecha de ingreso (ingreso + 3, 6, 9, 12… meses). Se calcula al
 *   último salario integral vigente a la fecha de cierre del trimestre.
 * - Días adicionales: 2 días de salario por cada año cumplido, en el mes del
 *   aniversario, acumulables hasta 30 días.
 * - Intereses: mensual simple sobre el saldo = saldo × (tasa BCV anual / 12 / 100),
 *   capitalizados al cierre de cada mes.
 * - Anticipos y préstamos vigentes del mes se descuentan del saldo.
 * - Moneda: la del sueldo del empleado (USD o Bs).
 *
 * Se calcula SIEMPRE a mes cerrado, generando un registro por empleado+periodo.
 */
class PrestacionesSocialesService
{
    public const DIAS_TRIMESTRE = 15;
    public const DIAS_ADICIONALES_ANIO = 2;
    public const DIAS_ADICIONALES_MAX = 30;
    public const MESES_TRIMESTRE = 3;
    public const MESES_ANNO = 12;

    public function __construct(
        private SalarioIntegralService $salarioIntegral = new SalarioIntegralService,
        private TasasInteresService $tasasInteres = new TasasInteresService,
    ) {
    }

    /**
     * Saldo acumulado de prestaciones de un empleado a una fecha (último
     * periodo calculado en o antes de la fecha).
     */
    public function saldoEn(Empleado $empleado, ?CarbonInterface $fecha = null): float
    {
        $fecha = $fecha ?: now();

        $registro = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '<=', $fecha->format('Y-m'))
            ->orderByDesc('periodo')
            ->first();

        return $registro ? (float) $registro->saldo_final : 0.0;
    }

    /**
     * Cuenta los trimestres cumplidos a una fecha (ingreso + 3N meses).
     */
    public function trimestresCumplidos(Empleado $empleado, CarbonInterface $fecha): int
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso);
        $fin = Carbon::instance($fecha);

        if ($fin->lt($ingreso)) {
            return 0;
        }

        return (int) floor($ingreso->diffInMonths($fin) / self::MESES_TRIMESTRE);
    }

    /**
     * Años de servicio completos a una fecha.
     */
    public function aniosServicio(Empleado $empleado, ?CarbonInterface $fecha = null): int
    {
        return $this->salarioIntegral->aniosServicio($empleado, $fecha ?: now());
    }

    /**
     * Indica si la fecha de cierre cae en el mes del aniversario del empleado
     * (mismo mes que su fecha de ingreso, con al menos un año cumplido).
     */
    public function esMesAniversario(Empleado $empleado, CarbonInterface $cierre): bool
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso);

        if ($this->aniosServicio($empleado, $cierre) < 1) {
            return false;
        }

        return $cierre->month === $ingreso->month;
    }

    /**
     * Días adicionales acumulados por antigüedad: 2×(años−1) con tope 30.
     * El primer abono se efectúa al cumplir 2 años de servicio.
     */
    public function diasAdicionalesAcumulados(Empleado $empleado, ?CarbonInterface $fecha = null): int
    {
        $anios = $this->aniosServicio($empleado, $fecha ?: now());

        return min(max($anios - 1, 0) * self::DIAS_ADICIONALES_ANIO, self::DIAS_ADICIONALES_MAX);
    }

    /**
     * Anticipos y préstamos vigentes registrados dentro de un mes calendario,
     * convertidos a la moneda del sueldo del empleado.
     */
    public function anticiposDelMes(Empleado $empleado, CarbonInterface $inicioMes, CarbonInterface $finMes, string $moneda): float
    {
        $movimientos = MovimientoPrestaciones::where('empleado_id', $empleado->id)
            ->where('estatus', '!=', 'anulado')
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get();

        $total = 0.0;

        foreach ($movimientos as $m) {
            if ($m->moneda === $moneda) {
                $total += (float) $m->monto;
                continue;
            }

            $total += $this->convertir((float) $m->monto, $m->moneda, $moneda, $inicioMes);
        }

        return round($total, 2);
    }

    /**
     * Calcula los valores de un mes completo.
     *
     * @return array{periodo:string, cierre:Carbon, anios_servicio:int, trimestre:int,
     *               salario_integral_diario:float, moneda:string, tasa_interes:?float,
     *               saldo_inicial:float, deposito_trimestre:float, dias_deposito:int,
     *               dias_adicionales:float, dias_adicionales_cont:int,
     *               interes_mes:float, anticipos:float, saldo_final:float}
     */
    public function calcularMes(Empleado $empleado, CarbonInterface $fechaDelMes): array
    {
        $cierre = Carbon::instance($fechaDelMes)->endOfMonth();
        $inicio = Carbon::instance($fechaDelMes)->startOfMonth();

        $integral = $this->salarioIntegral->diario($empleado, $cierre);
        $moneda = $integral['moneda'];
        $salarioIntegralDiario = (float) $integral['salario_diario_integral'];

        $periodo = $fechaDelMes->format('Y-m');
        $trimestre = $this->trimestresCumplidos($empleado, $cierre);
        $aniosServicio = $this->aniosServicio($empleado, $cierre);

        $saldoInicial = $this->saldoEn($empleado, $inicio->copy()->subDay());

        // Depósito trimestral: 15 días cuando el mes cumple trimestre desde el ingreso.
        $diasDeposito = 0;
        $depositoTrimestre = 0.0;
        if ($trimestre > 0 && $this->esMesCumpleTrimestre($empleado, $cierre)) {
            $diasDeposito = self::DIAS_TRIMESTRE;
            $depositoTrimestre = round(self::DIAS_TRIMESTRE * $salarioIntegralDiario, 2);
        }

        // Días adicionales (Art. 142 lit. b): abono creciente 2×(año−1) con
        // tope 30, primer abono al cumplir 2 años de servicio.
        $diasAdicionalesCont = 0;
        $diasAdicionales = 0.0;
        if ($this->esMesAniversario($empleado, $cierre) && $aniosServicio >= 2) {
            $diasAdicionalesCont = min(($aniosServicio - 1) * self::DIAS_ADICIONALES_ANIO, self::DIAS_ADICIONALES_MAX);
            $diasAdicionales = round($diasAdicionalesCont * $salarioIntegralDiario, 2);
        }

        // Intereses (Art. 143): mensual simple sobre el saldo, capitalizados.
        $tasaInteres = $this->tasasInteres->tasaEn($cierre);
        $interesMes = 0.0;
        if ($tasaInteres !== null && $saldoInicial > 0) {
            $interesMes = round($saldoInicial * ($tasaInteres / 100 / self::MESES_ANNO), 2);
        }

        $anticipos = $this->anticiposDelMes($empleado, $inicio, $cierre, $moneda);

        $saldoFinal = round(
            $saldoInicial + $depositoTrimestre + $diasAdicionales + $interesMes - $anticipos,
            2
        );

        return [
            'periodo'                 => $periodo,
            'cierre'                  => $cierre,
            'anios_servicio'          => $aniosServicio,
            'trimestre'               => $trimestre,
            'salario_integral_diario' => $salarioIntegralDiario,
            'moneda'                  => $moneda,
            'tasa_interes'            => $tasaInteres,
            'saldo_inicial'           => $saldoInicial,
            'deposito_trimestre'      => $depositoTrimestre,
            'dias_deposito'           => $diasDeposito,
            'dias_adicionales'        => $diasAdicionales,
            'dias_adicionales_cont'   => $diasAdicionalesCont,
            'interes_mes'             => $interesMes,
            'anticipos'               => $anticipos,
            'saldo_final'             => $saldoFinal,
        ];
    }

    /**
     * Indica si el mes de cierre es el mes en que se cumple un trimestre desde
     * la fecha de ingreso (ingreso + 3N meses).
     */
    public function esMesCumpleTrimestre(Empleado $empleado, CarbonInterface $cierre): bool
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso);
        $trimestre = $this->trimestresCumplidos($empleado, $cierre);

        if ($trimestre <= 0) {
            return false;
        }

        $fechaCumple = $ingreso->copy()->addMonths($trimestre * self::MESES_TRIMESTRE);

        return $fechaCumple->month === $cierre->month && $fechaCumple->year === $cierre->year;
    }

    /**
     * Registra un periodo calculado (update-or-insert por empleado+periodo).
     */
    public function registrarMes(Empleado $empleado, CarbonInterface $fechaDelMes): PrestacionSocial
    {
        $data = $this->calcularMes($empleado, $fechaDelMes);

        return PrestacionSocial::updateOrCreate(
            [
                'empleado_id' => $empleado->id,
                'periodo'     => $data['periodo'],
            ],
            [
                'patrono_id'              => $empleado->patrono_id,
                'fecha_cierre'            => $data['cierre']->toDateString(),
                'anios_servicio'          => $data['anios_servicio'],
                'trimestre'               => $data['trimestre'],
                'salario_integral_diario' => $data['salario_integral_diario'],
                'moneda'                  => $data['moneda'],
                'tasa_interes'            => $data['tasa_interes'],
                'saldo_inicial'           => $data['saldo_inicial'],
                'deposito_trimestre'      => $data['deposito_trimestre'],
                'dias_deposito'           => $data['dias_deposito'],
                'dias_adicionales'        => $data['dias_adicionales'],
                'dias_adicionales_cont'   => $data['dias_adicionales_cont'],
                'interes_mes'             => $data['interes_mes'],
                'anticipos'               => $data['anticipos'],
                'saldo_final'             => $data['saldo_final'],
            ]
        );
    }

    /**
     * Periodos (YYYY-MM) que deben existir para un empleado: desde el mes de
     * ingreso hasta el mes actual (cerrado).
     */
    public function periodosRequeridos(Empleado $empleado, ?CarbonInterface $hasta = null): array
    {
        $hasta = Carbon::instance($hasta ?: now());
        $inicio = Carbon::instance($empleado->fecha_ingreso)->startOfMonth();
        $fin = $hasta->startOfMonth();

        if ($fin->lt($inicio)) {
            return [];
        }

        $periodos = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fin)) {
            $periodos[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $periodos;
    }

    /**
     * Verifica y registra los periodos pendientes hasta el mes actual (a mes
     * cerrado). Solo crea los periodos faltantes.
     *
     * @return array{creados:int, existentes:int}
     */
    public function asegurarPeriodos(Empleado $empleado, ?CarbonInterface $hasta = null): array
    {
        $hasta = Carbon::instance($hasta ?: now());
        $requeridos = $this->periodosRequeridos($empleado, $hasta);

        if (empty($requeridos)) {
            return ['creados' => 0, 'existentes' => 0];
        }

        $existentes = PrestacionSocial::where('empleado_id', $empleado->id)
            ->whereIn('periodo', $requeridos)
            ->pluck('periodo')
            ->flip();

        $creados = 0;
        foreach ($requeridos as $periodo) {
            if ($existentes->has($periodo)) {
                continue;
            }
            $this->registrarMes($empleado, Carbon::createFromFormat('Y-m', $periodo));
            $creados++;
        }

        return ['creados' => $creados, 'existentes' => $existentes->count()];
    }

    /**
     * Regenera los periodos de un empleado desde un periodo (o todos) hacia
     * adelante hasta el mes actual.
     */
    public function regenerar(Empleado $empleado, ?string $desdePeriodo = null): int
    {
        $desde = $desdePeriodo
            ? Carbon::createFromFormat('Y-m', $desdePeriodo)
            : Carbon::instance($empleado->fecha_ingreso)->startOfMonth();

        $desde->startOfMonth();
        $hasta = now();

        $eliminados = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '>=', $desde->format('Y-m'))
            ->delete();

        $creados = 0;
        foreach ($this->periodosRequeridos($empleado, $hasta) as $periodo) {
            $inicioMes = Carbon::createFromFormat('Y-m', $periodo);
            if ($inicioMes->lt($desde)) {
                continue;
            }
            $this->registrarMes($empleado, $inicioMes);
            $creados++;
        }

        return $creados;
    }

    /**
     * Estado de cuenta completo del empleado (meses en orden ascendente).
     */
    public function estadoDeCuenta(Empleado $empleado): Collection
    {
        $this->asegurarPeriodos($empleado);

        return PrestacionSocial::with('empleado')
            ->where('empleado_id', $empleado->id)
            ->orderBy('periodo')
            ->get();
    }

    /**
     * Convierte un monto entre Bs y USD usando la tasa BCV de una fecha.
     */
    public function convertir(float $monto, string $desde, string $hacia, ?CarbonInterface $fecha = null): float
    {
        if ($desde === $hacia) {
            return $monto;
        }

        $tasas = new TasasBcvService;
        $tasa = $tasas->tasaEn($fecha ?: now());

        if (! $tasa || $tasa <= 0) {
            return $monto;
        }

        return $desde === 'USD'
            ? round($monto * $tasa, 2)
            : round($monto / $tasa, 2);
    }
}
