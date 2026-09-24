<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\Feriado;
use App\Models\Parametro;
use App\Models\Vacacion;
use App\Models\VacacionesRecibo;
use App\Models\Patrono;
use Carbon\Carbon;
use Carbon\CarbonInterface;
/**
 * Servicio para cálculos de vacaciones y bono vacacional (LOTTT).
 *
 * - Días vacaciones: 15 hábiles (año 1) + 1/hábil por año, tope 30.
 * - Bono vacacional: 15 días base + 1 por año, tope 30.
 * - Salario diario = salario_base / 30.
 * - Los descuentos legales (IVSS / Paro / FAOV) se aplican SOLO cuando el
 *   patrono los tiene marcados, sobre (salario_diario × días_remunerados).
 * - Base imponible = vacaciones + bono (días hábiles de disfrute + bono).
 */
class VacacionesService
{
    public const DIAS_BASE = 15;
    public const DIAS_MAX = 30;
    public const BONO_BASE = 15;
    public const DIAS_MES = 30;

    public const IVSR_PCT = 0.04;
    public const PARO_PCT = 0.005;
    public const FAOV_PCT = 0.01;

    /** Multiplicadores del salario mínimo para los topes semanales. */
    public const SM_MULT_IVSS = 5;
    public const SM_MULT_RLE = 10;

    public function __construct(
        private CalendarioLaboral $calendario = new CalendarioLaboral,
        private SueldosService $sueldos = new SueldosService,
    ) {
    }

    public function aniosServicio(Empleado $empleado, ?CarbonInterface $fecha = null): int
    {
        $fecha = $fecha ?: now();
        $ingreso = Carbon::instance($empleado->fecha_ingreso);

        return (int) $ingreso->diffInYears(Carbon::instance($fecha));
    }

    /**
     * Días por año de servicio: [vacaciones, adicionales, bono, bono_adicionales].
     * Techo del 1 al 15 adicionales (max 30).
     */
    public function diasPorAnios(int $aniosDeServicio): array
    {
        $adicionales = min(max($aniosDeServicio - 1, 0), self::DIAS_MAX - self::DIAS_BASE);

        return [
            'vacaciones'           => self::DIAS_BASE,
            'adicionales'          => $adicionales,
            'bono'                 => self::DIAS_BASE,
            'bono_adicionales'     => $adicionales,
        ];
    }

    /**
     * Años de servicio completos alcanzados (aniversarios cumplidos) para los
     * que aún no existe un periodo de vacaciones registrado como disfrutado/pagado.
     */
    public function aniversariosPendientes(Empleado $empleado, ?CarbonInterface $hasta = null): array
    {
        $hasta = Carbon::instance($hasta ?: now());
        $ingreso = Carbon::instance($empleado->fecha_ingreso);
        $anios = $this->aniosServicio($empleado, $hasta);

        $registrados = $empleado->vacaciones()
            ->whereIn('estatus_vacacion', ['pendiente', 'pagada', 'disfrutada'])
            ->whereIn('estatus_calculo', ['por_aprobar', 'aprobada'])
            ->pluck('nro_vacacion')
            ->all();

        $pendientes = [];
        for ($i = 1; $i <= max(1, $anios); $i++) {
            if (in_array($i, $registrados, true)) {
                continue;
            }
            $aniversario = $ingreso->copy()->addYears($i - 1);
            if ($aniversario <= $hasta) {
                $pendientes[] = [
                    'anio'        => $i,
                    'aniversario' => $aniversario->format('Y-m-d'),
                ];
            }
        }

        return $pendientes;
    }

    /**
     * Próximo periodo pendiente (más antiguo sin disfrutar/pagar).
     * Devuelve años de servicio, periodo trabajado (aniversario a aniversario)
     * y el aniversario base.
     *
     * @return array{destino:int, anio_servicio:int, periodo_desde:Carbon, periodo_hasta:Carbon, aniversario:Carbon}
     */
    public function proximoVacacionesPendiente(Empleado $empleado, ?CarbonInterface $hasta = null): array
    {
        $hasta = Carbon::instance($hasta ?: now());
        $ingreso = Carbon::instance($empleado->fecha_ingreso);
        $pendientes = $this->aniversariosPendientes($empleado, $hasta);

        if (empty($pendientes)) {
            // Todos los aniversarios cumplidos ya tienen un periodo registrado.
            // Avanza al siguiente periodo no disfrutado (próximo aniversario).
            $aniosCompletados = $this->aniosServicio($empleado, $hasta);
            $anio = $aniosCompletados + 1;
            $aniversario = $ingreso->copy()->addYears($anio);
        } else {
            $anio = (int) $pendientes[0]['anio'];
            $aniversario = Carbon::parse($pendientes[0]['aniversario']);
        }

        $periodoHasta = $ingreso->copy()->addYears($anio);
        $periodoDesde = $periodoHasta->copy()->subYear();

        return [
            'anio_servicio' => $anio,
            'periodo_desde' => $periodoDesde,
            'periodo_hasta' => $periodoHasta,
            'aniversario'   => $aniversario,
        ];
    }

    /**
     * Ajusta una fecha al primer día hábil (si cae en feriado/descanso).
     */
    public function ajustarAlHabil(CarbonInterface $fecha, Empleado $empleado): Carbon
    {
        $d = Carbon::instance($fecha);

        return $this->calendario->esDiaHabil($d, $empleado) ? $d : $this->calendario->proximoDiaHabil($d, $empleado);
    }

    /**
     * Calcula fechas reales de disfrute: inicio_real, fin_real, reintegro.
     * Cuenta el día de inicio como día 1 si es hábil; luego busca N hábiles.
     */
    public function calcularFechasReales(string $fechaInicio, int $diasHabiles, Empleado $empleado): array
    {
        $inicioReal = $this->ajustarAlHabil(Carbon::parse($fechaInicio), $empleado);
        $finReal = $this->calendario->sumarDiasHabiles($inicioReal, $diasHabiles, $empleado);
        $reintegro = $this->calendario->esDiaHabil($finReal, $empleado)
            ? $this->calendario->proximoDiaHabil($finReal, $empleado)
            : $inicioReal; // fallback teórico
        // reintegro siempre es el siguiente hábil después de fin_real
        $reintegro = $this->calendario->proximoDiaHabil($finReal, $empleado);

        return [
            'fecha_inicio_real' => $inicioReal,
            'fecha_fin_real'    => $finReal,
            'fecha_reintegro'   => $reintegro,
        ];
    }

    /**
     * Desglosa los días no hábiles dentro del rango real de disfrute
     * [inicio — fin] (inclusive) entre descanso semanal y feriados.
     *
     * Un día del rango que NO es hábil se clasifica como:
     * - 'descanso' si su weekday está en la config de descanso del empleado;
     * - 'feriado' en caso contrario (feriado en día laborero).
     * Así se evita el doble conteo (un feriado en sábado cuenta como descanso).
     *
     * @return array{dias_descanso:int, dias_feriados:int, dias_habiles:int}
     */
    public function desgloseNoHabiles(CarbonInterface $inicio, CarbonInterface $fin, Empleado $empleado): array
    {
        $d = Carbon::instance($inicio);
        $finD = Carbon::instance($fin);
        $descanso = $empleado->dias_descanso ? array_map(fn ($v) => (int) $v, explode(',', (string) $empleado->dias_descanso)) : CalendarioLaboral::DESCANSO_DEFAULT;

        $diasDescanso = 0;
        $diasFeriados = 0;
        $diasHabiles = 0;

        while ($d <= $finD) {
            if (in_array((int) $d->format('N'), $descanso, true)) {
                $diasDescanso++;
            } elseif ($this->calendario->esFeriado($d)) {
                $diasFeriados++;
            } else {
                $diasHabiles++;
            }
            $d->addDay();
        }

        return [
            'dias_descanso' => $diasDescanso,
            'dias_feriados' => $diasFeriados,
            'dias_habiles'  => $diasHabiles,
        ];
    }

    /**
     * Cálculo integral del periodo de disfrute.
     *
     * Usa automáticamente el periodo pendiente más antiguo (más antiguo sin
     * disfrute/pago). Ignora $anioDeServicio: se deriva del próximo aniversario
     * pendiente para garantizar coherencia entre días y periodo trabajado.
     *
     * @param int $anioDeServicio Años de servicio (ignorado; se usa el pendiente).
     * @param string|null $fechaInicioUser Fecha inicio propuesta por el usuario (Y-m-d).
     */
    public function calcular(Empleado $empleado, int $anioDeServicio, ?string $fechaInicioUser = null): array
    {
        $hasta = now();
        $pendiente = $this->proximoVacacionesPendiente($empleado, $hasta);

        $anioServicioReal = (int) $pendiente['anio_servicio'];
        $dias = $this->diasPorAnios($anioServicioReal);
        $diasVac = $dias['vacaciones'] + $dias['adicionales'];
        $diasBono = $dias['bono'] + $dias['bono_adicionales'];

        $aniversario = $pendiente['periodo_hasta'];
        $fechaInicioBase = $fechaInicioUser ?: $aniversario->toDateString();
        $fechas = $this->calcularFechasReales($fechaInicioBase, $diasVac, $empleado);

        $inicioReal = $fechas['fecha_inicio_real'];
        $finReal = $fechas['fecha_fin_real'];

        $feriados = $this->calendario->feriadosEnPeriodo($inicioReal, $finReal);
        $noHabiles = $this->desgloseNoHabiles($inicioReal, $finReal, $empleado);
        $diasDescanso = $noHabiles['dias_descanso'];
        $diasFeriados = $noHabiles['dias_feriados'];

        // Sueldo vigente a la fecha de inicio real del disfrute (fuente: sueldos).
        $vigente = $this->sueldos->sueldoVigente($empleado, $inicioReal);
        $salarioNormal = (float) $vigente['monto'];
        $moneda = $vigente['moneda'];

        $salarioDiario = $salarioNormal / self::DIAS_MES;

        $vacacionesImporte = round($diasVac * $salarioDiario, 2);
        $descansoImporte = round($diasDescanso * $salarioDiario, 2);
        $feriadosImporte = round($diasFeriados * $salarioDiario, 2);
        $bonoImporte = round($diasBono * $salarioDiario, 2);

        // Deducciones legales (Opción 2), coherentes con el recibo final.
        $diasNormales = $diasVac + $diasDescanso + $diasFeriados;
        $baseIntegral = round($salarioDiario * ($diasNormales + $diasBono), 2);
        $resultadoDed = $this->deduccionesOpcion2(
            $empleado,
            $empleado->patrono,
            $salarioNormal,
            (float) $diasNormales,
            (float) $diasBono,
            $inicioReal,
            $finReal,
            $baseIntegral,
            $moneda,
        );
        $totalAsignaciones = round($vacacionesImporte + $descansoImporte + $feriadosImporte + $bonoImporte, 2);
        $neto = round($totalAsignaciones - $resultadoDed['total_deducciones'], 2);

        return [
            'anio_servicio'        => $anioServicioReal,
            'periodo_desde'        => $pendiente['periodo_desde']->format('Y-m-d'),
            'periodo_hasta'        => $pendiente['periodo_hasta']->format('Y-m-d'),
            'aniversario'          => $aniversario->format('Y-m-d'),
            'fecha_inicio_user'    => $fechaInicioBase,
            'fecha_inicio_real'    => $inicioReal->format('Y-m-d'),
            'fecha_fin_real'       => $finReal->format('Y-m-d'),
            'fecha_reintegro'      => $fechas['fecha_reintegro']->format('Y-m-d'),
            'dias_vacaciones'      => $diasVac,
            'dias_vacaciones_base' => self::DIAS_BASE,
            'dias_vacaciones_adic' => $dias['adicionales'],
            'dias_bono'            => $diasBono,
            'dias_bono_base'       => self::BONO_BASE,
            'dias_bono_adic'       => $dias['bono_adicionales'],
            'dias_descanso'        => $diasDescanso,
            'dias_feriados'        => $diasFeriados,
            'salario_normal'       => $salarioNormal,
            'salario_diario'       => $salarioDiario,
            'moneda'               => $moneda,
            'asignaciones'         => [
                [
                    'concepto'       => 'Vacaciones',
                    'base_legal'     => 'Art. 190 LOTTT',
                    'dias'           => $diasVac,
                    'salario_diario' => $salarioDiario,
                    'importe'        => $vacacionesImporte,
                    'observacion'    => "{$diasVac} días hábiles",
                ],
                [
                    'concepto'       => 'Bono Vacacional',
                    'base_legal'     => 'Art. 192 LOTTT',
                    'dias'           => $diasBono,
                    'salario_diario' => $salarioDiario,
                    'importe'        => $bonoImporte,
                    'observacion'    => "{$diasBono} días de salario",
                ],
                [
                    'concepto'       => 'Días de Descanso Semanal',
                    'base_legal'     => 'Art. 196 LOTTT',
                    'dias'           => $diasDescanso,
                    'salario_diario' => $salarioDiario,
                    'importe'        => $descansoImporte,
                    'observacion'    => "{$diasDescanso} días de descanso en el disfrute",
                ],
                [
                    'concepto'       => 'Días Feriados',
                    'base_legal'     => 'Arts. 119 y 184 LOTTT',
                    'dias'           => $diasFeriados,
                    'salario_diario' => $salarioDiario,
                    'importe'        => $feriadosImporte,
                    'observacion'    => "{$diasFeriados} días feriados en el disfrute",
                ],
            ],
            'deducciones'      => $resultadoDed['deducciones'],
            'total_asignaciones' => $totalAsignaciones,
            'total_deducciones'  => $resultadoDed['total_deducciones'],
            'neto'              => $neto,
            'feriados_en_periodo'  => $feriados,
            '_salario_diario'      => $salarioDiario,
            '_dias_remunerados'    => $diasVac + $diasDescanso + $diasFeriados + $diasBono,
        ];
    }

    /**
     * Cuenta los lunes reales (enteros) dentro de un rango inclusive.
     * Representa las semanas calendario de cotización del período de disfrute.
     */
    public function lunesReales(CarbonInterface $inicio, CarbonInterface $fin): int
    {
        $d = Carbon::instance($inicio)->startOfDay();
        $finD = Carbon::instance($fin)->startOfDay();
        $contador = 0;

        while ($d->lte($finD)) {
            if ((int) $d->format('N') === 1) {
                $contador++;
            }
            $d->addDay();
        }

        return $contador;
    }

    /**
     * Deducciones legales (Opción 2):
     *  - IVSS  (4%) y Paro/RLE (0.5%): sobre semanas calendario (lunes reales)
     *    del disfrute, usando salario normal mensual en Bs, TOPEADO semanalmente
     *    por el salario mínimo vigente (IVSS 5×SM, RLE 10×SM); excluye el bono.
     *    El tope semanal en Bs se convierte primero a la moneda del recibo.
     *  - FAOV (1%): sobre el salario integral (hábiles + descanso + feriados + bono).
     *
     * Los importes legales (Bs) se convierten a la moneda del empleado.
     *
     * @return array{deducciones:array, total_deducciones:float}
     */
    public function deduccionesOpcion2(
        Empleado $empleado,
        Patrono $patrono,
        float $salarioNormal,
        float $diasNormales,
        float $diasBono,
        CarbonInterface $inicioReal,
        CarbonInterface $finReal,
        ?float $baseIntegral = null,
        ?string $moneda = null,
    ): array {
        $deducciones = [];
        $totalDeducciones = 0.0;

        // Moneda del recibo: la de la fuente (sueldos); respaldo a la ficha.
        $moneda = $moneda ?? $empleado->moneda;

        if ($baseIntegral === null) {
            $salarioDiario = $salarioNormal / self::DIAS_MES;
            $baseIntegral = round($salarioDiario * ($diasNormales + $diasBono), 2);
        }

        $semanasReales = $this->lunesReales($inicioReal, $finReal);
        $inicio = Carbon::instance($inicioReal);
        $salarioMin = salario_minimo_en($inicio);

        // Salario mensual en Bs (convierte de USD con tasa BCV del día).
        $tasaBcv = (new TasasBcvService)->tasaEn($inicio);
        $tasa = $tasaBcv ?? (float) Parametro::get('tasa_dolar', 0.0);
        $salarioMensualBs = $moneda === 'USD'
            ? $salarioNormal * ($tasa > 0 ? $tasa : 1)
            : $salarioNormal;

        $semanaNormalBs = $salarioMensualBs * 12 / 52;

        // Tope semanal legal convertido a la moneda del recibo: primero se
        // expresa el tope en Bs y luego se divide entre la tasa BCV del día.
        $factorDivisa = ($moneda === 'USD' && $tasa > 0) ? (1 / $tasa) : 1;

        if ($patrono->descuenta_ivss) {
            $topeBs = $salarioMin ? ($salarioMin['mensual_bs'] * self::SM_MULT_IVSS) * 12 / 52 : null;
            $baseSemanaBs = ($topeBs !== null) ? min($semanaNormalBs, $topeBs) : $semanaNormalBs;
            $topeSemanaMoneda = $baseSemanaBs * $factorDivisa;
            $importe = round($topeSemanaMoneda * $semanasReales * self::IVSR_PCT, 2);
            $topeTxt = $baseSemanaBs >= $semanaNormalBs
                ? 'sin tope aplicado'
                : 'topeada a ' . formato_monto($baseSemanaBs) . ' Bs/semana (≈ '
                    . formato_monto($topeSemanaMoneda) . ' ' . $moneda . '/semana)';
            $deducciones[] = [
                'concepto'   => 'Seguro Social (IVSS)',
                'base_legal' => '4% sobre base semanal topeada: ' . $topeTxt
                    . ($salarioMin ? ' (SM ' . $salarioMin['gaceta'] . ')' : ''),
                'porcentaje' => self::IVSR_PCT,
                'importe'    => $importe,
            ];
            $totalDeducciones += $importe;
        }

        if ($patrono->descuenta_paro_fijo) {
            $topeBs = $salarioMin ? ($salarioMin['mensual_bs'] * self::SM_MULT_RLE) * 12 / 52 : null;
            $baseSemanaBs = ($topeBs !== null) ? min($semanaNormalBs, $topeBs) : $semanaNormalBs;
            $topeSemanaMoneda = $baseSemanaBs * $factorDivisa;
            $importe = round($topeSemanaMoneda * $semanasReales * self::PARO_PCT, 2);
            $topeTxt = $baseSemanaBs >= $semanaNormalBs
                ? 'sin base aplicada'
                : 'topeada a ' . formato_monto($baseSemanaBs) . ' Bs/semana (≈ '
                    . formato_monto($topeSemanaMoneda) . ' ' . $moneda . '/semana)';
            $deducciones[] = [
                'concepto'    => 'Régimen de Paro (SP Empleo)',
                'base_legal'  => '0.5% sobre base semanal topeada: ' . $topeTxt
                    . ($salarioMin ? ' (SM ' . $salarioMin['gaceta'] . ')' : ''),
                'porcentaje'  => self::PARO_PCT,
                'importe'     => $importe,
            ];
            $totalDeducciones += $importe;
        }

        if ($patrono->descuenta_faov) {
            $importe = round($baseIntegral * self::FAOV_PCT, 2);
            $deducciones[] = [
                'concepto'    => 'FAOV',
                'base_legal'  => '1% sobre salario integral (incluye bono vacacional)',
                'porcentaje'  => self::FAOV_PCT,
                'importe'     => $importe,
            ];
            $totalDeducciones += $importe;
        }

        return [
            'deducciones'      => $deducciones,
            'total_deducciones' => round($totalDeducciones, 2),
        ];
    }

    /**
     * Genera (o regenera) el recibo de pago, aplicando sólo las deducciones
     * marcadas en el patrono del empleado.
     */
    public function generarRecibo(Vacacion $vacacion): VacacionesRecibo
    {
        $empleado = $vacacion->empleado;
        $patrono = $vacacion->patrono;

        $salarioDiario = (float) $vacacion->salario_normal / self::DIAS_MES;
        $diasHabiles = $vacacion->diasHabilesDisfrute();
        $diasBono = $vacacion->diasHabilesBono();

        // Desglose de descanso/feriados del disfrute (recalculado para robustez).
        $noHabiles = $this->desgloseNoHabiles(
            $vacacion->fecha_inicio_real,
            $vacacion->fecha_fin_real,
            $empleado
        );
        $diasDescanso = $vacacion->dias_descanso ?? $noHabiles['dias_descanso'];
        $diasFeriados = $vacacion->dias_feriados ?? $noHabiles['dias_feriados'];

        // Días de salario normal: hábiles de disfrute + descanso + feriados.
        $diasNormales = $diasHabiles + $diasDescanso + $diasFeriados;
        // Base FAOV (salario integral): incluye además el bono vacacional.
        $baseIntegral = round($salarioDiario * ($diasNormales + $diasBono), 2);

        $asignaciones = [
            [
                'concepto'       => 'Vacaciones',
                'base_legal'     => 'Art. 190 LOTTT',
                'dias'           => $diasHabiles,
                'salario_diario' => round($salarioDiario, 2),
                'importe'        => round($diasHabiles * $salarioDiario, 2),
                'observacion'    => "{$diasHabiles} días hábiles",
            ],
            [
                'concepto'       => 'Bono Vacacional',
                'base_legal'     => 'Art. 192 LOTTT',
                'dias'           => $diasBono,
                'salario_diario' => round($salarioDiario, 2),
                'importe'        => round($diasBono * $salarioDiario, 2),
                'observacion'    => "{$diasBono} días de salario",
            ],
            [
                'concepto'       => 'Días de Descanso Semanal',
                'base_legal'     => 'Art. 196 LOTTT',
                'dias'           => $diasDescanso,
                'salario_diario' => round($salarioDiario, 2),
                'importe'        => round($diasDescanso * $salarioDiario, 2),
                'observacion'    => "{$diasDescanso} días de descanso en el disfrute",
            ],
            [
                'concepto'       => 'Días Feriados',
                'base_legal'     => 'Arts. 119 y 184 LOTTT',
                'dias'           => $diasFeriados,
                'salario_diario' => round($salarioDiario, 2),
                'importe'        => round($diasFeriados * $salarioDiario, 2),
                'observacion'    => "{$diasFeriados} días feriados en el disfrute",
            ],
        ];

        // Opción 2 (jurídica): IVSS / Paro / FAOV según las reglas legales.
        $resultadoDed = $this->deduccionesOpcion2(
            $empleado,
            $patrono,
            (float) $vacacion->salario_normal,
            $diasNormales,
            $diasBono,
            $vacacion->fecha_inicio_real,
            $vacacion->fecha_fin_real,
            $baseIntegral,
            $vacacion->moneda,
        );
        $deducciones = $resultadoDed['deducciones'];
        $totalDeducciones = $resultadoDed['total_deducciones'];

        $totalAsignaciones = round(array_sum(array_column($asignaciones, 'importe')), 2);
        $totalDeducciones = round($totalDeducciones, 2);
        $neto = round($totalAsignaciones - $totalDeducciones, 2);

        return $vacacion->recibos()->create([
            'empleado_id'       => $empleado->id,
            'fecha_emision'     => now()->toDateString(),
            'fecha_inicio'      => $vacacion->fecha_inicio_real,
            'fecha_retiro'      => $vacacion->fecha_reintegro,
            'asignaciones'      => $asignaciones,
            'deducciones'       => $deducciones,
            'total_asignaciones' => $totalAsignaciones,
            'total_deducciones'  => $totalDeducciones,
            'neto'              => $neto,
            'moneda'            => $vacacion->moneda,
            'tipo_cambio'       => $this->tasaEnDia(now()->toDateString()),
        ]);
    }

    /**
     * Formatea un monto: muestra el equivalente en Bs. sólo cuando la moneda
     * es USD y existe tasa configurada y el patrono tiene activado
     * `mostrar_equivalencia_usd`. La moneda proviene de la fuente (sueldos),
     * con respaldo a la ficha del empleado.
     */
    public function formatearMonto(Empleado $empleado, float $monto, ?float $tasa = null, bool $mostrar = null, ?string $moneda = null): array
    {
        $moneda = $moneda ?? $empleado->moneda;

        if ($moneda === 'USD') {
            $tasa = $tasa ?? $this->tasaEnDia(now()->toDateString());
            $mostrar = $mostrar ?? (bool) ($empleado->patrono->mostrar_equivalencia_usd ?? false);

            return [
                'moneda'      => 'USD',
                'principal'   => 'USD ' . formato_monto($monto),
                'equivalente' => ($tasa > 0 && $mostrar) ? '≈ Bs. ' . formato_monto($monto * $tasa) : null,
            ];
        }

        return [
            'moneda'      => 'BS',
            'principal'   => 'Bs. ' . formato_monto($monto),
            'equivalente' => null,
        ];
    }

    /**
     * Tasa BCV para una fecha (o la más reciente emitida antes), con respaldo
     * al parámetro global.
     */
    public function tasaEnDia(string $fecha): float
    {
        $tasaBcv = (new TasasBcvService)->tasaEn(Carbon::parse($fecha));

        return $tasaBcv ?? (float) Parametro::get('tasa_dolar', 0.0);
    }

    /**
     * Consulta con paginación y filtros para el reporte institucional.
     * Devuelve colección paginada de Vacacion + relaciones + computed stats.
     */
    public function reporteVacaciones(
        Patrono $patrono,
        array $filtros,
        int $porPagina = 15,
    ): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Vacacion::with('empleado')
            ->where('patrono_id', $patrono->id)
            ->orderByDesc('fecha_calculo')
            ->orderByDesc('id');

        // año (periodo_hasta)
        if (isset($filtros['ano']) && $filtros['ano']) {
            $query->whereYear('periodo_hasta', $filtros['ano']);
        }

        // estatus cálculo
        if (isset($filtros['estatus_calculo']) && $filtros['estatus_calculo']) {
            $query->where('estatus_calculo', $filtros['estatus_calculo']);
        }

        // estatus vacación
        if (isset($filtros['estatus_vacacion']) && $filtros['estatus_vacacion']) {
            $query->where('estatus_vacacion', $filtros['estatus_vacacion']);
        }

        // empleado (por defecto null)
        if (isset($filtros['empleado_id']) && $filtros['empleado_id']) {
            $query->where('empleado_id', $filtros['empleado_id']);
        }

        return $query->paginate($porPagina);
    }

    /**
     * Listado completo (sin paginación) para exportar el reporte a PDF,
     * cargando trabajador y último recibo cuando exista.
     */
    public function reporteVacacionesExport(Patrono $patrono, array $filtros): \Illuminate\Database\Eloquent\Collection
    {
        $query = Vacacion::with(['empleado', 'recibo'])
            ->where('patrono_id', $patrono->id)
            ->orderBy('periodo_hasta')
            ->orderByDesc('id');

        if (isset($filtros['ano']) && $filtros['ano']) {
            $query->whereYear('periodo_hasta', $filtros['ano']);
        }
        if (isset($filtros['estatus_calculo']) && $filtros['estatus_calculo']) {
            $query->where('estatus_calculo', $filtros['estatus_calculo']);
        }
        if (isset($filtros['estatus_vacacion']) && $filtros['estatus_vacacion']) {
            $query->where('estatus_vacacion', $filtros['estatus_vacacion']);
        }
        if (isset($filtros['empleado_id']) && $filtros['empleado_id']) {
            $query->where('empleado_id', $filtros['empleado_id']);
        }

        return $query->get();
    }

    /**
     * Estadísticas globales para el reporte: total de filas, días disfrutados,
     * días bono, asignaciones totales, deducciones totales, neto pagado.
     * Considera solo filas dentro del patrono y filtros aplicados.
     */
    public function estadisticasVacaciones(Patrono $patrono, array $filtros): array
    {
        $query = Vacacion::where('patrono_id', $patrono->id);

        // aplicar mismos filtros que en el reporte
        if (isset($filtros['ano']) && $filtros['ano']) {
            $query->whereYear('periodo_hasta', $filtros['ano']);
        }
        if (isset($filtros['estatus_calculo']) && $filtros['estatus_calculo']) {
            $query->where('estatus_calculo', $filtros['estatus_calculo']);
        }
        if (isset($filtros['estatus_vacacion']) && $filtros['estatus_vacacion']) {
            $query->where('estatus_vacacion', $filtros['estatus_vacacion']);
        }
        if (isset($filtros['empleado_id']) && $filtros['empleado_id']) {
            $query->where('empleado_id', $filtros['empleado_id']);
        }

        $vacaciones = $query->get();

        // total filas
        $totalRegistros = $vacaciones->count();

        // días hábiles totales de disfrute (vacaciones + adicionales)
        $totalDiasVacaciones = $vacaciones->sum(fn($v) => $v->diasHabilesDisfrute());

        // días de bono totales (bono + bono_adicionales)
        $totalDiasBono = $vacaciones->sum(fn($v) => $v->diasHabilesBono());

        // sumar recibo totales (solo para filas pagada/disfrutada)
        $totalAsignaciones = 0.0;
        $totalDeducciones = 0.0;
        $totalNeto = 0.0;

        foreach ($vacaciones as $v) {
            $recibo = $v->recibos()->latest('fecha_emision')->first();
            if ($recibo) {
                $totalAsignaciones += (float) $recibo->total_asignaciones;
                $totalDeducciones += (float) $recibo->total_deducciones;
                $totalNeto += (float) $recibo->neto;
            }
        }

        return [
            'totalRegistros'     => $totalRegistros,
            'totalDiasVacaciones'=> $totalDiasVacaciones,
            'totalDiasBono'      => $totalDiasBono,
            'totalAsignaciones'  => $totalAsignaciones,
            'totalDeducciones'   => $totalDeducciones,
            'totalNeto'          => $totalNeto,
        ];
    }
}