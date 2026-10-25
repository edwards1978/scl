<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use App\Models\MovimientoPrestaciones;
use App\Models\PagoInteresPrestaciones;
use App\Models\PrestacionSocial;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Servicio de prestaciones sociales (garantía Art. 142 LOTTT) e intereses
 * (Art. 143 LOTTT).
 *
 * Reglas vigentes desde el 23/10/2026:
 * - Depósito trimestral: 15 días de salario integral por cada trimestre.
 * - Días adicionales: se abona el acumulado completo en cada aniversario
 *   (2, 4, 6… días), con tope de 30.
 * - Interés: simple mensual sobre el CAPITAL de la garantía.
 * - El interés no pagado se capitaliza en el mes del aniversario del ingreso.
 * - Los anticipos reducen el capital; los préstamos NO reducen la garantía
 *   (son deuda separada).
 * - Los pagos de intereses se registran en `pagos_interes_prestaciones`.
 *
 * Se calcula siempre a mes cerrado, con un registro por empleado + periodo.
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
        $registro = $this->registroEn($empleado, $fecha);

        return $registro ? (float) $registro->saldo_final : 0.0;
    }

    /** Último período calculado en o antes de la fecha. */
    public function registroEn(Empleado $empleado, ?CarbonInterface $fecha = null): ?PrestacionSocial
    {
        $fecha = $fecha ?: now();

        return PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '<=', $fecha->format('Y-m'))
            ->orderByDesc('periodo')
            ->first();
    }

    /** Capital de la garantía (sin interés pendiente) a una fecha. */
    public function capitalEn(Empleado $empleado, ?CarbonInterface $fecha = null): float
    {
        $registro = $this->registroEn($empleado, $fecha);

        if (! $registro) {
            return 0.0;
        }

        return (float) ($registro->capital_final ?? $registro->saldo_final);
    }

    /**
     * Garantía BRUTA a una fecha: saldo neto + anticipos otorgados.
     * Se usa para comparar con el cálculo de 30 días/año antes de descontar
     * los anticipos una sola vez.
     */
    public function garantiaBrutaEn(Empleado $empleado, ?CarbonInterface $fecha = null): float
    {
        $registro = $this->registroEn($empleado, $fecha);

        if (! $registro) {
            return 0.0;
        }

        $anticipos = $this->totalAnticipos($empleado, (string) $registro->moneda, $fecha);

        return round((float) $registro->saldo_final + $anticipos, 2);
    }

    /**
     * Total de anticipos no anulados hasta una fecha, convertidos a la moneda
     * de la garantía. Los préstamos no forman parte de este total.
     */
    public function totalAnticipos(Empleado $empleado, string $moneda, ?CarbonInterface $hasta = null): float
    {
        $movimientos = MovimientoPrestaciones::where('empleado_id', $empleado->id)
            ->where('tipo', MovimientoPrestaciones::TIPO_ANTICIPO)
            ->where('estatus', '!=', 'anulado')
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta->toDateString()))
            ->get();

        $total = 0.0;
        foreach ($movimientos as $m) {
            $monto = (float) $m->monto;
            $total += $m->moneda === $moneda
                ? $monto
                : $this->convertir($monto, (string) $m->moneda, $moneda, $m->fecha);
        }

        return round($total, 2);
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
     * Anticipos (no préstamos) registrados dentro de un mes calendario,
     * convertidos a la moneda del sueldo del empleado.
     */
    public function anticiposDelMes(Empleado $empleado, CarbonInterface $inicioMes, CarbonInterface $finMes, string $moneda): float
    {
        $movimientos = MovimientoPrestaciones::where('empleado_id', $empleado->id)
            ->where('tipo', MovimientoPrestaciones::TIPO_ANTICIPO)
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
     * Pagos de intereses registrados dentro de un mes, convertidos a la
     * moneda de la garantía.
     */
    public function pagosDelMes(Empleado $empleado, CarbonInterface $inicioMes, CarbonInterface $finMes, string $moneda): float
    {
        $pagos = PagoInteresPrestaciones::where('empleado_id', $empleado->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get();

        $total = 0.0;
        foreach ($pagos as $p) {
            $monto = (float) $p->monto;
            $total += $p->moneda === $moneda
                ? $monto
                : $this->convertir($monto, (string) $p->moneda, $moneda, $p->fecha);
        }

        return round($total, 2);
    }

    /**
     * Calcula los valores de un mes completo.
     *
     * @return array{periodo:string, cierre:Carbon, anios_servicio:int, trimestre:int,
     *               salario_integral_diario:float, moneda:string, tasa_interes:?float,
     *               saldo_inicial:float, capital_inicial:float, capital_final:float,
     *               interes_pendiente_inicial:float, interes_pendiente_final:float,
     *               deposito_trimestre:float, dias_deposito:int,
     *               dias_adicionales:float, dias_adicionales_cont:int,
     *               interes_mes:float, interes_capitalizado:float, interes_pagado:float,
     *               anticipos:float, saldo_final:float}
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

        $previo = $this->registroEn($empleado, $inicio->copy()->subDay());
        $capitalInicial = $previo
            ? (float) ($previo->capital_final ?? $previo->saldo_final)
            : 0.0;
        $interesPendienteInicial = $previo && $previo->interes_pendiente_final !== null
            ? (float) $previo->interes_pendiente_final
            : 0.0;
        $saldoInicial = round($capitalInicial + $interesPendienteInicial, 2);

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

        // Interés simple del mes sobre el capital (no sobre el interés pendiente).
        $tasaInteres = $this->tasasInteres->tasaEn($cierre);
        $interesMes = 0.0;
        if ($tasaInteres !== null && $capitalInicial > 0) {
            $interesMes = round($capitalInicial * ($tasaInteres / 100 / self::MESES_ANNO), 2);
        }

        $anticipos = $this->anticiposDelMes($empleado, $inicio, $cierre, $moneda);
        $pagosInteres = $this->pagosDelMes($empleado, $inicio, $cierre, $moneda);

        // Los pagos cubren primero el interés pendiente; el exceso reduce capital.
        $pendienteAntesPago = round($interesPendienteInicial + $interesMes, 2);
        $pagoAplicado = min($pagosInteres, max(0.0, $pendienteAntesPago));
        $pendienteDespuesPago = round($pendienteAntesPago - $pagoAplicado, 2);
        $excesoPago = round($pagosInteres - $pagoAplicado, 2);

        $capital = $capitalInicial + $depositoTrimestre + $diasAdicionales - $anticipos - $excesoPago;
        $capital = max(0.0, round($capital, 2));

        // Capitalización del interés vencido y no pagado en el aniversario.
        $interesCapitalizado = 0.0;
        $interesPendienteFinal = $pendienteDespuesPago;
        if ($this->esMesAniversario($empleado, $cierre) && $pendienteDespuesPago > 0) {
            $interesCapitalizado = $pendienteDespuesPago;
            $capital = round($capital + $interesCapitalizado, 2);
            $interesPendienteFinal = 0.0;
        }

        $capitalFinal = round($capital, 2);
        $interesPendienteFinal = round($interesPendienteFinal, 2);
        $saldoFinal = round($capitalFinal + $interesPendienteFinal, 2);

        return [
            'periodo'                    => $periodo,
            'cierre'                     => $cierre,
            'anios_servicio'             => $aniosServicio,
            'trimestre'                  => $trimestre,
            'salario_integral_diario'    => $salarioIntegralDiario,
            'moneda'                     => $moneda,
            'tasa_interes'               => $tasaInteres,
            'saldo_inicial'              => $saldoInicial,
            'capital_inicial'            => round($capitalInicial, 2),
            'capital_final'              => $capitalFinal,
            'interes_pendiente_inicial'  => round($interesPendienteInicial, 2),
            'interes_pendiente_final'    => $interesPendienteFinal,
            'deposito_trimestre'         => $depositoTrimestre,
            'dias_deposito'              => $diasDeposito,
            'dias_adicionales'           => $diasAdicionales,
            'dias_adicionales_cont'      => $diasAdicionalesCont,
            'interes_mes'                => $interesMes,
            'interes_capitalizado'       => $interesCapitalizado,
            'interes_pagado'             => $pagosInteres,
            'anticipos'                  => $anticipos,
            'saldo_final'                => $saldoFinal,
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
                'capital_inicial'         => $data['capital_inicial'],
                'capital_final'           => $data['capital_final'],
                'interes_pendiente_inicial' => $data['interes_pendiente_inicial'],
                'interes_pendiente_final' => $data['interes_pendiente_final'],
                'deposito_trimestre'      => $data['deposito_trimestre'],
                'dias_deposito'           => $data['dias_deposito'],
                'dias_adicionales'        => $data['dias_adicionales'],
                'dias_adicionales_cont'   => $data['dias_adicionales_cont'],
                'interes_mes'             => $data['interes_mes'],
                'interes_capitalizado'    => $data['interes_capitalizado'],
                'interes_pagado'          => $data['interes_pagado'],
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($empleado, $desde, $hasta) {
            PrestacionSocial::where('empleado_id', $empleado->id)
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
        });
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
     * Estado de cuenta filtrado por período (YYYY-MM), con totales de flujo
     * del rango y acumulados hasta el cierre del rango.
     *
     * No recalcula prestaciones: usa los saldos ya calculados y separa
     * movimientos (flujos) de saldos (stock).
     */
    public function estadoDeCuentaEnRango(Empleado $empleado, ?string $desde = null, ?string $hasta = null): array
    {
        $cuenta = $this->estadoDeCuenta($empleado);

        $desde = $this->normalizarPeriodo($desde);
        $hasta = $this->normalizarPeriodo($hasta);

        $hayRango = $desde !== null || $hasta !== null;
        $primerPeriodo = $cuenta->first()?->periodo;
        $ultimoPeriodo = $cuenta->last()?->periodo;

        $desdeEfectivo = $desde ?? $primerPeriodo;
        $hastaEfectivo = $hasta ?? $ultimoPeriodo;

        $filas = $cuenta->filter(function ($c) use ($desdeEfectivo, $hastaEfectivo) {
            return ($desdeEfectivo === null || $c->periodo >= $desdeEfectivo)
                && ($hastaEfectivo === null || $c->periodo <= $hastaEfectivo);
        })->values();

        // Acumulado desde el inicio de la historia hasta el cierre del rango.
        $acumuladas = $cuenta->filter(function ($c) use ($hastaEfectivo) {
            return $hastaEfectivo === null || $c->periodo <= $hastaEfectivo;
        })->values();

        $primera = $filas->first();
        $ultimaHasta = $acumuladas->last();
        $anterior = $cuenta->filter(function ($c) use ($desdeEfectivo) {
            return $desdeEfectivo !== null && $c->periodo < $desdeEfectivo;
        })->last();

        $saldoInicial = $primera
            ? (float) $primera->saldo_inicial
            : ($anterior ? (float) $anterior->saldo_final : 0.0);

        // El cierre siempre queda acotado a la fecha "hasta", nunca al último
        // período global de la historia.
        $saldoFinal = $ultimaHasta
            ? (float) $ultimaHasta->saldo_final
            : $saldoInicial;
        $interesPendiente = $ultimaHasta
            ? (float) ($ultimaHasta->interes_pendiente_final ?? 0)
            : 0.0;

        return [
            'filas'              => $filas,
            'desde'              => $desdeEfectivo,
            'hasta'              => $hastaEfectivo,
            'hay_rango'          => $hayRango,
            'saldo_inicial'      => round($saldoInicial, 2),
            'saldo_final'        => round($saldoFinal, 2),
            // Alias de compatibilidad: representa el cierre hasta "hasta",
            // no el saldo global más reciente.
            'saldo_actual'       => round($saldoFinal, 2),
            'interes_pendiente'  => round($interesPendiente, 2),
            'total_periodos'     => $cuenta->count(),
            'periodos_filtrados' => $filas->count(),
            'totales_periodo'    => $this->totalesFlujo($filas),
            'totales_acumulados' => $this->totalesFlujo($acumuladas),
        ];
    }

    /**
     * Totales de movimientos (no de saldos). El interés capitalizado es
     * informativo y no se suma al neto porque ya está en `interes_mes`.
     */
    private function totalesFlujo(Collection $filas): array
    {
        $depositos = round((float) $filas->sum('deposito_trimestre'), 2);
        $adicionales = round((float) $filas->sum('dias_adicionales'), 2);
        $intereses = round((float) $filas->sum('interes_mes'), 2);
        $capitalizado = round((float) $filas->sum('interes_capitalizado'), 2);
        $pagados = round((float) $filas->sum('interes_pagado'), 2);
        $anticipos = round((float) $filas->sum('anticipos'), 2);

        return [
            'depositos'        => $depositos,
            'adicionales'      => $adicionales,
            'intereses'        => $intereses,
            'capitalizado'     => $capitalizado,
            'pagados'          => $pagados,
            'anticipos'        => $anticipos,
            'neto'             => round($depositos + $adicionales + $intereses - $anticipos - $pagados, 2),
            'dias_deposito'    => (int) $filas->sum('dias_deposito'),
            'dias_adicionales' => (int) $filas->sum('dias_adicionales_cont'),
        ];
    }

    /** Normaliza un período YYYY-MM; devuelve null si viene vacío o inválido. */
    private function normalizarPeriodo(?string $periodo): ?string
    {
        $periodo = trim((string) $periodo);

        if ($periodo === '' || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            return null;
        }

        return $periodo;
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
