<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de prestaciones sociales (garantía Art. 142 LOTTT) e intereses
 * (Art. 143 LOTTT).
 *
 * Reglas vigentes desde el 23/10/2026:
 * - Depósito trimestral: 15 días de salario integral al cierre de cada
 *   trimestre (último día del mes en que se cumple). Si la relación termina
 *   antes de completar el trimestre, se abona la fracción de 15 días.
 * - Días adicionales: al cierre del período que completa cada año se abonan
 *   2, 4, 6… días (2 por cada año desde el segundo), con tope de 30 por
 *   abono. Desde el año 16 se abonan 30 días cada año (no 32).
 * - Interés: simple mensual sobre el CAPITAL de la garantía al cierre del mes
 *   anterior (capital_inicial), a la tasa promedio entre activa y pasiva del
 *   BCV. No se recalcula por los anticipos ni pagos del propio mes.
 * - Solo se calculan intereses cuando la garantía está abonada en la
 *   contabilidad del patrono. En fideicomiso o fondo los calcula la entidad.
 * - El interés no pagado se capitaliza en el aniversario SOLO si el trabajador
 *   tiene una autorización escrita registrada.
 * - Sin autorización, el interés queda pendiente y no genera intereses.
 * - Los anticipos reducen el capital; los préstamos NO reducen la garantía
 *   (son deuda separada).
 * - Los pagos de intereses se registran en `pagos_interes_prestaciones`.
 *
 * Los períodos son meses de servicio anclados al día de ingreso: el período
 * 1 va del ingreso al día anterior del mismo día del mes siguiente. Así, un
 * ingreso el 11/05 cierra su primer período el 10/06.
 *
 * Se calcula siempre a período cerrado, con un registro por empleado + período
 * (clave YYYY-MM del mes en que inicia el período).
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
        private TasasBcvService $tasasBcv = new TasasBcvService,
    ) {}

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
    public function registroEn(Empleado $empleado, ?CarbonInterface $fecha = null, bool $incluirProyeccion = false): ?PrestacionSocial
    {
        $fecha = $fecha ?: now();

        $egreso = $this->fechaEgresoVigente($empleado);
        if ($egreso && $fecha->gt($egreso)) {
            $fecha = $egreso;
        }

        $periodo = $this->periodoDeFecha($empleado, $fecha);

        if ($periodo === null) {
            return null;
        }

        return PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '<=', $periodo)
            ->when(! $incluirProyeccion, fn ($q) => $q->where('es_proyeccion', false))
            ->orderByDesc('periodo')
            ->first();
    }

    /**
     * Fecha de egreso del trabajador según su liquidación aprobada. Se usa como
     * tope para no devengar prestaciones después del cese, incluso si el
     * registro quedó marcado como activo por una inconsistencia previa.
     */
    public function fechaEgresoVigente(Empleado $empleado): ?Carbon
    {
        $fecha = Liquidacion::where('empleado_id', $empleado->id)
            ->where('estatus', 'aprobada')
            ->orderByDesc('fecha_egreso')
            ->value('fecha_egreso');

        return $fecha ? Carbon::parse($fecha)->startOfDay() : null;
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
     * Interés pendiente a una fecha, en la moneda de la garantía y convertido
     * a USD/Bs con la tasa BCV vigente en esa fecha.
     *
     * Incluye los períodos proyectados cuando la fecha es futura: la
     * proyección es la base operativa para pagar intereses de un período que
     * todavía no ha concluido.
     *
     * @return array{periodo:?string, moneda:string, pendiente:float, tasa:?float,
     *               usd:?float, bs:?float, es_proyeccion:bool}
     */
    public function interesPendienteEn(Empleado $empleado, ?CarbonInterface $fecha = null): array
    {
        $fecha = $fecha ?: now();
        $registro = $this->registroEn($empleado, $fecha, true);
        $moneda = $registro?->moneda ?? (string) $empleado->moneda;
        $pendiente = $registro ? (float) ($registro->interes_pendiente_final ?? 0) : 0.0;
        $tasa = $this->tasasBcv->tasaEn($fecha);

        $usd = null;
        $bs = null;
        if ($moneda === 'USD') {
            $usd = round($pendiente, 2);
            $bs = $tasa ? round($pendiente * $tasa, 2) : null;
        } elseif ($moneda === 'BS') {
            $bs = round($pendiente, 2);
            $usd = $tasa ? round($pendiente / $tasa, 2) : null;
        }

        return [
            'periodo' => $registro?->periodo,
            'moneda' => $moneda,
            'pendiente' => round($pendiente, 2),
            'tasa' => $tasa,
            'usd' => $usd,
            'bs' => $bs,
            'es_proyeccion' => (bool) $registro?->es_proyeccion,
        ];
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
     * Garantía bruta a la fecha de egreso, calculando el período de egreso en
     * memoria con corte en esa fecha. Así se incluye la fracción del trimestre
     * en curso sin esperar a que la liquidación esté aprobada. No persiste.
     */
    public function garantiaBrutaAlEgreso(Empleado $empleado, CarbonInterface $egreso): float
    {
        $periodo = $this->periodoDeFecha($empleado, $egreso);
        $mes = $periodo
            ? Carbon::createFromFormat('Y-m', $periodo)
            : Carbon::instance($egreso);

        $calculo = $this->calcularMes($empleado, $mes, false, $egreso);
        $anticipos = $this->totalAnticipos($empleado, $calculo['moneda'], $egreso);

        return round($calculo['saldo_final'] + $anticipos, 2);
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
     * Último día del trimestre número $numero desde el ingreso.
     *
     * El mes del ingreso cuenta como mes 1: un ingreso el 01/01 cierra su
     * primer trimestre el 31/03 (ingreso + 3 meses − 1 día). Se usa
     * addMonthsNoOverflow para que un ingreso el 31 no desborde al mes
     * siguiente.
     */
    public function finTrimestre(Empleado $empleado, int $numero): Carbon
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso)->startOfDay();

        return $ingreso->copy()
            ->addMonthsNoOverflow($numero * self::MESES_TRIMESTRE)
            ->subDay();
    }

    /**
     * Inicio real del período identificado por el mes en que empieza
     * (YYYY-MM). El período 1 arranca el día de ingreso y cada período
     * siguiente el mismo día del mes, sin desbordar meses cortos.
     */
    public function inicioPeriodo(Empleado $empleado, CarbonInterface $mes): Carbon
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso)->startOfDay();
        $indice = max(0, (int) round($ingreso->copy()->startOfMonth()->diffInMonths(
            Carbon::instance($mes)->startOfMonth()
        )));

        return $ingreso->copy()->addMonthsNoOverflow($indice);
    }

    /** Último día del período identificado por su mes de inicio (YYYY-MM). */
    public function finPeriodo(Empleado $empleado, CarbonInterface $mes): Carbon
    {
        return $this->inicioPeriodo($empleado, $mes)
            ->addMonthsNoOverflow(1)
            ->subDay();
    }

    /**
     * Clave (YYYY-MM) del período de servicio que contiene una fecha, o null
     * si la fecha es anterior al ingreso.
     */
    public function periodoDeFecha(Empleado $empleado, CarbonInterface $fecha): ?string
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso)->startOfDay();
        $fecha = Carbon::instance($fecha)->startOfDay();

        if ($fecha->lt($ingreso)) {
            return null;
        }

        $indice = max(0, (int) floor($ingreso->diffInMonths($fecha)));

        while ($ingreso->copy()->addMonthsNoOverflow($indice + 1)->lte($fecha)) {
            $indice++;
        }

        while ($indice > 0 && $ingreso->copy()->addMonthsNoOverflow($indice)->gt($fecha)) {
            $indice--;
        }

        return $ingreso->copy()->addMonthsNoOverflow($indice)->format('Y-m');
    }

    /**
     * Tiempo de servicio acumulado al cierre de un período. Cada período
     * completo suma un mes exacto; el período de egreso suma además los días
     * trabajados desde el inicio del período.
     *
     * @return array{anios:int, meses:int, dias:int}
     */
    public function tiempoServicio(Empleado $empleado, CarbonInterface $inicio): array
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso)->startOfDay();
        $inicio = Carbon::instance($inicio)->startOfDay();
        $cierre = $inicio->copy()->addMonthsNoOverflow(1)->subDay();
        $egreso = $this->fechaEgresoVigente($empleado);

        $indice = max(0, (int) round($ingreso->diffInMonths($inicio)));

        $esParcial = $egreso !== null
            && $egreso->betweenIncluded($inicio, $cierre)
            && $egreso->lt($cierre);

        $meses = $esParcial ? $indice : $indice + 1;
        $dias = $esParcial ? (int) $inicio->diffInDays($egreso) + 1 : 0;

        return [
            'anios' => intdiv($meses, 12),
            'meses' => $meses % 12,
            'dias' => $dias,
        ];
    }

    /**
     * Cuenta los trimestres cerrados a una fecha (último día de cada trimestre
     * menor o igual a la fecha).
     */
    public function trimestresCumplidos(Empleado $empleado, CarbonInterface $fecha): int
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso)->startOfDay();
        $fin = Carbon::instance($fecha)->startOfDay();

        if ($fin->lt($ingreso)) {
            return 0;
        }

        $trimestres = max(0, (int) floor($ingreso->diffInMonths($fin) / self::MESES_TRIMESTRE));

        while ($this->finTrimestre($empleado, $trimestres + 1)->lte($fin)) {
            $trimestres++;
        }

        while ($trimestres > 0 && $this->finTrimestre($empleado, $trimestres)->gt($fin)) {
            $trimestres--;
        }

        return $trimestres;
    }

    /**
     * Años de servicio completos a una fecha.
     */
    public function aniosServicio(Empleado $empleado, ?CarbonInterface $fecha = null): int
    {
        return $this->salarioIntegral->aniosServicio($empleado, $fecha ?: now());
    }

    /**
     * Número de año de servicio que se completa con el cierre del período;
     * null si el período no completa un año.
     *
     * El año se cumple al final del día anterior al aniversario, así que el
     * abono corresponde al período que cierra ese día, no al siguiente. Si el
     * egreso cae justo en el aniversario, ese día también cuenta como
     * trabajado y el abono se hace en el período de egreso.
     */
    public function aniversarioEnPeriodo(Empleado $empleado, CarbonInterface $inicio, CarbonInterface $cierre): ?int
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso)->startOfDay();
        $desde = Carbon::instance($inicio)->startOfDay();
        $hasta = Carbon::instance($cierre)->startOfDay();

        if ($hasta->lt($ingreso)) {
            return null;
        }

        $estimado = (int) $ingreso->diffInYears($hasta);

        foreach ([$estimado, $estimado + 1] as $anio) {
            if ($anio < 1) {
                continue;
            }

            $aniversario = $ingreso->copy()->addYearsNoOverflow($anio);
            $ultimoDiaDelAnio = $aniversario->copy()->subDay();

            if ($ultimoDiaDelAnio->betweenIncluded($desde, $hasta)
                || $hasta->isSameDay($aniversario)) {
                return $anio;
            }
        }

        return null;
    }

    /**
     * Indica si el período completa un año de servicio (con al menos un año
     * cumplido).
     */
    public function esPeriodoAniversario(Empleado $empleado, CarbonInterface $inicio, CarbonInterface $cierre): bool
    {
        return $this->aniversarioEnPeriodo($empleado, $inicio, $cierre) !== null;
    }

    /**
     * Días adicionales que corresponden al último aniversario cumplido:
     * 2×(años−1) con tope de 30. El primer abono se efectúa al cumplir
     * 2 años de servicio.
     */
    public function diasAdicionalesAcumulados(Empleado $empleado, ?CarbonInterface $fecha = null): int
    {
        $anios = $this->aniosServicio($empleado, $fecha ?: now());

        return min(max($anios - 1, 0) * self::DIAS_ADICIONALES_ANIO, self::DIAS_ADICIONALES_MAX);
    }

    /**
     * Días adicionales que se abonan en un aniversario: 2 por cada año desde
     * el segundo (2, 4, 6…), con tope de 30 por abono. Desde el año 16 se
     * abonan 30 días cada año.
     */
    public function diasAdicionalesDelAniversario(int $anio): int
    {
        if ($anio < 2) {
            return 0;
        }

        return min(
            ($anio - 1) * self::DIAS_ADICIONALES_ANIO,
            self::DIAS_ADICIONALES_MAX
        );
    }

    /**
     * Anticipos (no préstamos) registrados dentro de un período de servicio,
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
     * Pagos de intereses registrados dentro de un período de servicio,
     * convertidos a la moneda de la garantía.
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
     * Calcula los valores de un período de servicio completo.
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
    public function calcularMes(
        Empleado $empleado,
        CarbonInterface $fechaDelMes,
        bool $incluirProyeccion = false,
        ?CarbonInterface $fechaEgreso = null,
    ): array {
        $mes = Carbon::instance($fechaDelMes)->startOfMonth();
        $periodo = $mes->format('Y-m');
        $inicio = $this->inicioPeriodo($empleado, $mes);
        $cierre = $this->finPeriodo($empleado, $mes);

        $egreso = $fechaEgreso
            ? Carbon::instance($fechaEgreso)->startOfDay()
            : $this->fechaEgresoVigente($empleado);
        $esPeriodoEgreso = $egreso !== null && $egreso->betweenIncluded($inicio, $cierre);

        // El período de egreso cierra en la fecha de egreso, no al fin del
        // período: así se paga solo la fracción efectivamente trabajada.
        if ($esPeriodoEgreso && $egreso->lt($cierre)) {
            $cierre = $egreso->copy();
        }

        $integral = $this->salarioIntegral->diario($empleado, $cierre);
        $moneda = $integral['moneda'];
        $salarioIntegralDiario = (float) $integral['salario_diario_integral'];

        $trimestre = $this->trimestresCumplidos($empleado, $cierre);
        $aniosServicio = $this->aniosServicio($empleado, $cierre);

        $previo = $this->registroEn($empleado, $inicio->copy()->subDay(), $incluirProyeccion);
        $capitalInicial = $previo
            ? (float) ($previo->capital_final ?? $previo->saldo_final)
            : 0.0;
        $interesPendienteInicial = $previo && $previo->interes_pendiente_final !== null
            ? (float) $previo->interes_pendiente_final
            : 0.0;
        $saldoInicial = round($capitalInicial + $interesPendienteInicial, 2);

        // Depósito trimestral: 15 días al cierre del trimestre, que coincide
        // con el último día del período de servicio número múltiplo de 3.
        $finUltimoTrimestre = $trimestre > 0 ? $this->finTrimestre($empleado, $trimestre) : null;
        $diasDeposito = 0;
        $depositoTrimestre = 0.0;

        if ($finUltimoTrimestre && $finUltimoTrimestre->isSameDay($cierre)) {
            $diasDeposito += self::DIAS_TRIMESTRE;
        }

        // Fracción del trimestre en curso al egreso: si la relación termina
        // antes de completarlo, se abona una sola vez la fracción de 15 días.
        if ($esPeriodoEgreso) {
            $inicioContrato = Carbon::instance($empleado->fecha_ingreso)->startOfDay();
            $hayFraccion = $finUltimoTrimestre
                ? $cierre->gt($finUltimoTrimestre)
                : $cierre->gt($inicioContrato);

            if ($hayFraccion && $diasDeposito === 0) {
                $diasDeposito += self::DIAS_TRIMESTRE;
            }
        }

        $depositoTrimestre = round($diasDeposito * $salarioIntegralDiario, 2);

        // Días adicionales (Art. 142 lit. b): 2, 4, 6… días en cada
        // aniversario desde el segundo, con tope de 30 por abono.
        $aniversario = $this->aniversarioEnPeriodo($empleado, $inicio, $cierre);
        $diasAdicionalesCont = $aniversario !== null
            ? $this->diasAdicionalesDelAniversario($aniversario)
            : 0;
        $diasAdicionales = round($diasAdicionalesCont * $salarioIntegralDiario, 2);

        // Interés simple del mes sobre el capital del mes anterior
        // (capital_inicial), no sobre el saldo del propio mes ni sobre el
        // interés pendiente. Así el interés no cambia si en el mismo mes se
        // registran anticipos o pagos de intereses.
        // Solo la modalidad "contabilidad" se calcula en SCL; en fideicomiso o
        // fondo los intereses los determina la entidad correspondiente.
        $modalidadDeposito = $empleado->patrono?->deposito_tipo ?? 'contabilidad';
        $tasaInteres = null;
        $interesMes = 0.0;
        if ($modalidadDeposito === 'contabilidad') {
            $tasaInteres = $this->tasasInteres->tasaEn($cierre);
            if ($tasaInteres !== null && $capitalInicial > 0) {
                $interesMes = round($capitalInicial * ($tasaInteres / 100 / self::MESES_ANNO), 2);
            }
        }

        $anticipos = $this->anticiposDelMes($empleado, $inicio, $cierre, $moneda);
        $pagosInteres = $this->pagosDelMes($empleado, $inicio, $cierre, $moneda);

        // Los pagos cubren primero el interés pendiente; el exceso reduce capital.
        $pendienteAntesPago = round($interesPendienteInicial + $interesMes, 2);
        $pagoAplicado = min($pagosInteres, max(0.0, $pendienteAntesPago));
        $pendienteDespuesPago = round($pendienteAntesPago - $pagoAplicado, 2);
        $excesoPago = round($pagosInteres - $pagoAplicado, 2);

        $capital = max(0.0, round(
            $capitalInicial + $depositoTrimestre + $diasAdicionales - $anticipos - $excesoPago,
            2
        ));

        // Capitalización del interés vencido y no pagado en el aniversario:
        // solo con autorización escrita vigente y modalidad contabilidad.
        $interesCapitalizado = 0.0;
        $interesPendienteFinal = $pendienteDespuesPago;
        $autorizaCapitalizacion = $modalidadDeposito === 'contabilidad'
            && (bool) $empleado->capitaliza_intereses
            && $empleado->capitalizacion_autorizada_desde !== null
            && $empleado->capitalizacion_autorizada_desde->lte($cierre);

        if ($autorizaCapitalizacion
            && $this->esPeriodoAniversario($empleado, $inicio, $cierre)
            && $pendienteDespuesPago > 0) {
            $interesCapitalizado = $pendienteDespuesPago;
            $capital = round($capital + $interesCapitalizado, 2);
            $interesPendienteFinal = 0.0;
        }

        $capitalFinal = round($capital, 2);
        $interesPendienteFinal = round($interesPendienteFinal, 2);
        $saldoFinal = round($capitalFinal + $interesPendienteFinal, 2);

        return [
            'periodo' => $periodo,
            'cierre' => $cierre,
            'anios_servicio' => $aniosServicio,
            'trimestre' => $trimestre,
            'salario_integral_diario' => $salarioIntegralDiario,
            'moneda' => $moneda,
            'tasa_interes' => $tasaInteres,
            'saldo_inicial' => $saldoInicial,
            'capital_inicial' => round($capitalInicial, 2),
            'capital_final' => $capitalFinal,
            'interes_pendiente_inicial' => round($interesPendienteInicial, 2),
            'interes_pendiente_final' => $interesPendienteFinal,
            'deposito_trimestre' => $depositoTrimestre,
            'dias_deposito' => $diasDeposito,
            'dias_adicionales' => $diasAdicionales,
            'dias_adicionales_cont' => $diasAdicionalesCont,
            'interes_mes' => $interesMes,
            'interes_capitalizado' => $interesCapitalizado,
            'interes_pagado' => $pagosInteres,
            'anticipos' => $anticipos,
            'saldo_final' => $saldoFinal,
        ];
    }

    /**
     * Indica si el cierre coincide con el último día de un trimestre cumplido
     * desde la fecha de ingreso.
     */
    public function esMesCumpleTrimestre(Empleado $empleado, CarbonInterface $cierre): bool
    {
        $trimestre = $this->trimestresCumplidos($empleado, $cierre);

        if ($trimestre <= 0) {
            return false;
        }

        return $this->finTrimestre($empleado, $trimestre)
            ->isSameDay(Carbon::instance($cierre)->startOfDay());
    }

    /**
     * Registra un periodo calculado (update-or-insert por empleado+periodo).
     */
    public function registrarMes(Empleado $empleado, CarbonInterface $fechaDelMes, bool $esProyeccion = false): PrestacionSocial
    {
        $data = $this->calcularMes($empleado, $fechaDelMes, $esProyeccion);

        return PrestacionSocial::updateOrCreate(
            [
                'empleado_id' => $empleado->id,
                'periodo' => $data['periodo'],
            ],
            [
                'patrono_id' => $empleado->patrono_id,
                'fecha_cierre' => $data['cierre']->toDateString(),
                'anios_servicio' => $data['anios_servicio'],
                'trimestre' => $data['trimestre'],
                'salario_integral_diario' => $data['salario_integral_diario'],
                'moneda' => $data['moneda'],
                'tasa_interes' => $data['tasa_interes'],
                'saldo_inicial' => $data['saldo_inicial'],
                'capital_inicial' => $data['capital_inicial'],
                'capital_final' => $data['capital_final'],
                'interes_pendiente_inicial' => $data['interes_pendiente_inicial'],
                'interes_pendiente_final' => $data['interes_pendiente_final'],
                'deposito_trimestre' => $data['deposito_trimestre'],
                'dias_deposito' => $data['dias_deposito'],
                'dias_adicionales' => $data['dias_adicionales'],
                'dias_adicionales_cont' => $data['dias_adicionales_cont'],
                'interes_mes' => $data['interes_mes'],
                'interes_capitalizado' => $data['interes_capitalizado'],
                'interes_pagado' => $data['interes_pagado'],
                'anticipos' => $data['anticipos'],
                'saldo_final' => $data['saldo_final'],
                'es_proyeccion' => $esProyeccion,
            ]
        );
    }

    /**
     * Períodos (YYYY-MM del mes de inicio) que deben existir para un empleado:
     * desde el mes de ingreso hasta el período que contiene la fecha de corte
     * (el mes actual o su egreso, si lo tiene).
     */
    public function periodosRequeridos(Empleado $empleado, ?CarbonInterface $hasta = null): array
    {
        $hasta = Carbon::instance($hasta ?: now());

        $egreso = $this->fechaEgresoVigente($empleado);
        if ($egreso && $hasta->gt($egreso)) {
            $hasta = $egreso->copy();
        }

        $ultimo = $this->periodoDeFecha($empleado, $hasta);

        if ($ultimo === null) {
            return [];
        }

        $inicio = Carbon::instance($empleado->fecha_ingreso)->startOfMonth();
        $fin = Carbon::createFromFormat('Y-m', $ultimo)->startOfMonth();

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
     * cerrado) o hasta el egreso del trabajador. Solo crea los faltantes.
     *
     * @return array{creados:int, existentes:int}
     */
    public function asegurarPeriodos(Empleado $empleado, ?CarbonInterface $hasta = null): array
    {
        // Los períodos reales nunca se generan hacia el futuro. Las
        // proyecciones se crean explícitamente con proyectarHasta().
        $limite = Carbon::instance(now())->endOfMonth();
        $hasta = Carbon::instance($hasta ?: $limite);
        if ($hasta->gt($limite)) {
            $hasta = $limite;
        }

        $egreso = $this->fechaEgresoVigente($empleado);
        if ($egreso && $hasta->gt($egreso)) {
            $hasta = $egreso;
        }

        $requeridos = $this->periodosRequeridos($empleado, $hasta);

        if (empty($requeridos)) {
            return ['creados' => 0, 'existentes' => 0];
        }

        // Una fila proyectada no cuenta como período real: si el mes ya venció,
        // se recalcula y pasa a es_proyeccion = false.
        $existentes = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', false)
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

        return DB::transaction(function () use ($empleado, $desde, $hasta) {
            // Una proyección deja de ser válida si se recalculan los datos
            // reales: se elimina y el usuario puede volver a proyectar.
            PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('es_proyeccion', true)
                ->delete();

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
     * Genera y guarda una proyección mensual desde el mes siguiente al último
     * período real hasta "$hasta" (máximo 12 meses hacia adelante).
     *
     * La proyección usa la última tasa BCV disponible y el último salario
     * conocido; queda marcada con es_proyeccion = true.
     */
    public function proyectarHasta(Empleado $empleado, string $hasta): int
    {
        return $this->proyectarEmpleado($empleado, $this->mesProyeccionValido($hasta));
    }

    /**
     * Recalcula desde un período conservando el horizonte de proyección.
     *
     * Los períodos reales se regeneran hasta el mes actual; si existía una
     * proyección guardada (o el movimiento es futuro), se reconstruye hasta
     * ese horizonte. Así un movimiento con fecha futura queda reflejado en la
     * proyección sin que esta se pierda.
     */
    public function recalcularDesde(Empleado $empleado, string $desdePeriodo): int
    {
        $desde = Carbon::createFromFormat('Y-m', $desdePeriodo)->startOfMonth();
        $mesActual = Carbon::instance(now())->startOfMonth();

        return DB::transaction(function () use ($empleado, $desde, $mesActual) {
            // Garantiza la base real hasta el mes actual antes de proyectar,
            // incluso si el movimiento es futuro.
            $this->asegurarPeriodos($empleado);

            // El horizonte se captura antes de borrar: la proyección debe
            // reconstruirse hasta donde estaba y, si el movimiento es futuro,
            // hasta su propio mes para que el saldo operativo lo refleje.
            $horizonte = PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('es_proyeccion', true)
                ->max('periodo');

            $objetivo = collect([
                $horizonte,
                $desde->gt($mesActual) ? $desde->format('Y-m') : null,
            ])->filter()->max();

            PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('es_proyeccion', true)
                ->delete();

            $creados = 0;

            if ($desde->lte($mesActual)) {
                $desdeReal = $desde->format('Y-m');

                PrestacionSocial::where('empleado_id', $empleado->id)
                    ->where('periodo', '>=', $desdeReal)
                    ->delete();

                foreach ($this->periodosRequeridos($empleado) as $periodo) {
                    if ($periodo < $desdeReal) {
                        continue;
                    }
                    $this->registrarMes($empleado, Carbon::createFromFormat('Y-m', $periodo));
                    $creados++;
                }
            }

            if ($objetivo !== null) {
                $hasta = Carbon::createFromFormat('Y-m', $objetivo)->startOfMonth();
                $limite = $mesActual->copy()->addMonths(12);
                if ($hasta->gt($limite)) {
                    $hasta = $limite->copy();
                }

                for ($mes = $mesActual->copy()->addMonth(); $mes->lte($hasta); $mes->addMonth()) {
                    $this->registrarMes($empleado, $mes, true);
                }
            }

            return $creados;
        });
    }

    /**
     * Garantiza una proyección hasta el mes de la fecha indicada, sin acortar
     * una proyección ya guardada. Se usa antes de validar un movimiento futuro
     * para que el saldo operativo lo contemple.
     */
    public function asegurarProyeccionHasta(Empleado $empleado, CarbonInterface $fecha): int
    {
        $mes = Carbon::instance($fecha)->startOfMonth();
        $limite = Carbon::instance(now())->startOfMonth();

        if ($mes->lte($limite)) {
            return 0;
        }

        $horizonte = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', true)
            ->max('periodo');

        if ($horizonte !== null) {
            $guardado = Carbon::createFromFormat('Y-m', $horizonte)->startOfMonth();
            if ($guardado->gte($mes)) {
                return 0;
            }
        }

        return $this->proyectarEmpleado($empleado, $this->mesProyeccionValido($mes->format('Y-m')));
    }

    /**
     * Genera/guarda la proyección de todos los trabajadores activos del patrono.
     *
     * @return array{trabajadores:int, periodos:int}
     */
    public function proyectarActivos(Patrono $patrono, string $hasta): array
    {
        $hastaMes = $this->mesProyeccionValido($hasta);
        $trabajadores = 0;
        $periodos = 0;

        foreach ($patrono->empleados()->where('estatus', 'Activo')->get() as $empleado) {
            $periodos += $this->proyectarEmpleado($empleado, $hastaMes);
            $trabajadores++;
        }

        return ['trabajadores' => $trabajadores, 'periodos' => $periodos];
    }

    private function mesProyeccionValido(string $hasta): Carbon
    {
        $hastaMes = Carbon::createFromFormat('Y-m', $hasta)->startOfMonth();
        $limite = Carbon::instance(now())->startOfMonth();
        $maximo = $limite->copy()->addMonths(12);

        if ($hastaMes->lte($limite)) {
            throw ValidationException::withMessages([
                'hasta' => 'La proyección debe ser para un mes posterior al mes actual.',
            ]);
        }

        if ($hastaMes->gt($maximo)) {
            throw ValidationException::withMessages([
                'hasta' => 'La proyección no puede superar los 12 meses hacia adelante.',
            ]);
        }

        return $hastaMes;
    }

    private function proyectarEmpleado(Empleado $empleado, Carbon $hastaMes): int
    {
        $limite = Carbon::instance(now())->startOfMonth();

        return DB::transaction(function () use ($empleado, $hastaMes, $limite) {
            // Asegura los períodos reales hasta el mes actual.
            $this->asegurarPeriodos($empleado, $limite->copy()->endOfMonth());

            // Una nueva proyección reemplaza la anterior.
            PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('es_proyeccion', true)
                ->delete();

            $ultimoReal = PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('es_proyeccion', false)
                ->orderByDesc('periodo')
                ->first();

            $inicio = $ultimoReal
                ? Carbon::createFromFormat('Y-m', $ultimoReal->periodo)->addMonth()->startOfMonth()
                : Carbon::instance($empleado->fecha_ingreso)->startOfMonth();

            if ($inicio->lte($limite)) {
                $inicio = $limite->copy()->addMonth()->startOfMonth();
            }

            $creados = 0;
            for ($mes = $inicio->copy(); $mes->lte($hastaMes); $mes->addMonth()) {
                if ($mes->lte($limite)) {
                    continue;
                }

                $this->registrarMes($empleado, $mes, true);
                $creados++;
            }

            return $creados;
        });
    }

    /** Elimina las proyecciones guardadas de un empleado. */
    public function eliminarProyeccion(Empleado $empleado): int
    {
        return PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', true)
            ->delete();
    }

    /**
     * Último período real (no proyectado) del empleado, respetando su fecha de
     * egreso. Es el cierre por defecto de PDF y hoja de cálculo.
     */
    public function ultimoPeriodoReal(Empleado $empleado): ?string
    {
        return $this->ultimoPeriodo($empleado, false);
    }

    /**
     * Último período guardado, incluyendo las proyecciones. Es el cierre por
     * defecto de la pantalla para que la proyección siga visible hasta que se
     * elimine o se recalculen los datos reales.
     */
    public function ultimoPeriodoConProyeccion(Empleado $empleado): ?string
    {
        return $this->ultimoPeriodo($empleado, true);
    }

    /** Fecha de cierre del último período guardado (real o proyectado). */
    public function ultimoCierreConProyeccion(Empleado $empleado): ?Carbon
    {
        return $this->ultimoRegistro($empleado, true)?->fecha_cierre;
    }

    /**
     * Saldo final del último período guardado (real o proyectado). Es el saldo
     * que corresponde a la fecha mostrada en "Calculado hasta".
     */
    public function saldoConProyeccion(Empleado $empleado): float
    {
        return (float) ($this->ultimoRegistro($empleado, true)?->saldo_final ?? 0);
    }

    private function ultimoPeriodo(Empleado $empleado, bool $incluirProyeccion): ?string
    {
        return $this->ultimoRegistro($empleado, $incluirProyeccion)?->periodo;
    }

    private function ultimoRegistro(Empleado $empleado, bool $incluirProyeccion): ?PrestacionSocial
    {
        $egreso = $this->fechaEgresoVigente($empleado);
        $hasta = $egreso ? $this->periodoDeFecha($empleado, $egreso) : null;

        return PrestacionSocial::where('empleado_id', $empleado->id)
            ->when(! $incluirProyeccion, fn ($q) => $q->where('es_proyeccion', false))
            ->when($hasta, fn ($q) => $q->where('periodo', '<=', $hasta))
            ->orderByDesc('periodo')
            ->first();
    }

    /**
     * Estado de cuenta completo del empleado (meses en orden ascendente).
     * Es una consulta de solo lectura: los períodos se generan en las rutas
     * POST que registran movimientos o recalculan.
     */
    public function estadoDeCuenta(Empleado $empleado): Collection
    {
        $egreso = $this->fechaEgresoVigente($empleado);
        $hasta = $egreso ? $this->periodoDeFecha($empleado, $egreso) : null;

        return PrestacionSocial::with('empleado')
            ->where('empleado_id', $empleado->id)
            ->when($hasta, fn ($q) => $q->where('periodo', '<=', $hasta))
            ->orderBy('periodo')
            ->get()
            ->each(function (PrestacionSocial $registro) use ($empleado) {
                $inicio = $this->inicioPeriodo(
                    $empleado,
                    Carbon::createFromFormat('Y-m', $registro->periodo)
                );

                $registro->setAttribute('periodo_inicio', $inicio);
                $registro->setAttribute(
                    'periodo_fin',
                    $registro->fecha_cierre
                        ? Carbon::instance($registro->fecha_cierre)->startOfDay()
                        : $inicio->copy()->addMonthsNoOverflow(1)->subDay()
                );

                $servicio = $this->tiempoServicio($empleado, $inicio);
                $registro->setAttribute('servicio_anios', $servicio['anios']);
                $registro->setAttribute('servicio_meses', $servicio['meses']);
                $registro->setAttribute('servicio_dias', $servicio['dias']);

                return $registro;
            });
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
            'filas' => $filas,
            'desde' => $desdeEfectivo,
            'hasta' => $hastaEfectivo,
            'hay_rango' => $hayRango,
            'saldo_inicial' => round($saldoInicial, 2),
            'saldo_final' => round($saldoFinal, 2),
            // Alias de compatibilidad: representa el cierre hasta "hasta",
            // no el saldo global más reciente.
            'saldo_actual' => round($saldoFinal, 2),
            'interes_pendiente' => round($interesPendiente, 2),
            'total_periodos' => $cuenta->count(),
            'periodos_filtrados' => $filas->count(),
            'totales_periodo' => $this->totalesFlujo($filas),
            'totales_acumulados' => $this->totalesFlujo($acumuladas),
        ];
    }

    /**
     * Ajusta los saldos de las filas para que la tabla también respete el
     * checkbox: si no se incluyen saldos anteriores, la primera fila empieza
     * en cero y las demás muestran el acumulado del período.
     *
     * No modifica la base de datos: son atributos calculados para la vista.
     */
    public function filasConSaldosDelPeriodo(Collection $filas, bool $incluirAnteriores): Collection
    {
        $primera = $filas->first();

        if (! $primera) {
            return $filas;
        }

        $primerSaldo = (float) ($primera->saldo_inicial ?? 0);
        $primerCapital = (float) ($primera->capital_inicial ?? 0);
        $primerInteres = (float) ($primera->interes_pendiente_inicial ?? 0);

        $baseSaldo = $incluirAnteriores ? $primerSaldo : 0.0;
        $baseCapital = $incluirAnteriores ? $primerCapital : 0.0;
        $baseInteres = $incluirAnteriores ? $primerInteres : 0.0;

        return $filas->map(function ($c) use ($baseSaldo, $baseCapital, $baseInteres, $primerSaldo, $primerCapital, $primerInteres) {
            $c->saldo_inicial_periodo = round($baseSaldo + ((float) ($c->saldo_inicial ?? 0) - $primerSaldo), 2);
            $c->saldo_final_periodo = round($baseSaldo + ((float) ($c->saldo_final ?? 0) - $primerSaldo), 2);
            $c->capital_periodo = round($baseCapital + ((float) ($c->capital_final ?? 0) - $primerCapital), 2);
            $c->interes_pendiente_periodo = round($baseInteres + ((float) ($c->interes_pendiente_final ?? 0) - $primerInteres), 2);

            return $c;
        });
    }

    /**
     * Totales de movimientos del rango. El interés capitalizado es
     * informativo porque ya está en `interes_mes`.
     *
     * El neto es la variación real del saldo (capital + interés pendiente) y
     * no la suma de los movimientos: un pago de intereses puede exceder el
     * interés pendiente y el capital disponible, y ese exceso no reduce el
     * saldo, por lo que la suma de movimientos no cuadraría con la tabla.
     */
    private function totalesFlujo(Collection $filas): array
    {
        $depositos = round((float) $filas->sum('deposito_trimestre'), 2);
        $adicionales = round((float) $filas->sum('dias_adicionales'), 2);
        $intereses = round((float) $filas->sum('interes_mes'), 2);
        $capitalizado = round((float) $filas->sum('interes_capitalizado'), 2);
        $pagados = round((float) $filas->sum('interes_pagado'), 2);
        $anticipos = round((float) $filas->sum('anticipos'), 2);
        $neto = round((float) $filas->sum(
            fn ($c) => (float) $c->saldo_final - (float) $c->saldo_inicial
        ), 2);

        return [
            'depositos' => $depositos,
            'adicionales' => $adicionales,
            'intereses' => $intereses,
            'capitalizado' => $capitalizado,
            'pagados' => $pagados,
            'anticipos' => $anticipos,
            'neto' => $neto,
            'dias_deposito' => (int) $filas->sum('dias_deposito'),
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
