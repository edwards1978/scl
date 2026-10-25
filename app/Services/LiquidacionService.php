<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Servicio de liquidación de prestaciones sociales (Art. 142 literal c LOTTT).
 *
 * - Prestaciones sociales: 30 días por año completo + 30 días si la fracción
 *   supera los 6 meses, al último salario integral diario.
 * - Indemnización por terminación (Art. 92 LOTTT): se dispara sólo cuando el
 *   motivo es por causa ajena a la voluntad del trabajador, y equivale al
 *   VALOR de las prestaciones sociales (no se expresa en días). Se muestra
 *   como un renglón sin días ni salario, justo debajo de los intereses.
 * - Los renglones que se calculan con salario integral lo indican
 *   (`tipo_salario` = integral | normal).
 * - Deducciones de ley: cada una en su propio renglón (IVSS, Paro, FAOV,
 *   INCES) según los flags de configuración del patrono.
 * - Conceptos almacenados en `liquidacion_conceptos` (tipo asignacion/deduccion,
 *   con días, salario diario, tipo de salario, importe, base legal y orden).
 *   Los renglones son editables antes de guardar.
 *
 * Orden de renglones:
 *   Asignaciones: Prestaciones, Intereses, Indemnización (si aplica),
 *   Vacaciones fraccionadas, Bono vacacional fraccionado, Utilidades fraccionadas.
 *   Deducciones: Anticipo, Pago de intereses, Préstamos (saldo), luego cada
 *   deducción de ley habilitada.
 */
class LiquidacionService
{
    public const DIAS_MES = 30;
    public const DIAS_ANNO = 360;

    public const IVSS_PCT = 0.04;
    public const PARO_PCT = 0.005;
    public const FAOV_PCT = 0.01;
    public const INCES_PCT = 0.005;

    /** Motivos de terminación considerados "causa ajena a la voluntad". */
    public const MOTIVOS_CAUSA_AJENA = [
        'despido_no_justificado',
        'retiro_justificado',
        'causa_ajena',
    ];

    public function __construct(
        private SalarioIntegralService $salarioIntegral = new SalarioIntegralService,
        private TasasBcvService $tasas = new TasasBcvService,
        private PrestacionesSocialesService $prestaciones = new PrestacionesSocialesService,
    ) {
    }

    /**
     * Años completos y meses de la fracción (redondeando la fracción del mes a
     * mes entero: días >= 15 del mes cuenta como mes completo).
     *
     * @return array{anios:int, meses:int}
     */
    public function antiguedad(Empleado $empleado, CarbonInterface $egreso): array
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso)->startOfDay();
        $fin = Carbon::instance($egreso)->startOfDay();

        $anios = (int) $ingreso->diffInYears($fin);
        $resto = $ingreso->copy()->addYears($anios);
        $meses = (int) $resto->diffInMonths($fin);

        // Fracción residual en días dentro del mes siguiente.
        $restoMeses = $resto->copy()->addMonths($meses);
        $diasResiduales = (int) $restoMeses->diffInDays($fin);

        if ($diasResiduales >= 15) {
            $meses++;
        }

        return ['anios' => $anios, 'meses' => $meses];
    }

    /**
     * Días de prestaciones Art. 142 literal c: 30 por año + 30 si la fracción
     * supera los 6 meses.
     */
    public function diasPrestaciones(int $anios, int $mesesFraccion): int
    {
        $dias = $anios * 30;

        if ($mesesFraccion > 6) {
            $dias += 30;
        }

        return $dias;
    }

    /**
     * Indica si el motivo de terminación es causa ajena a la voluntad del
     * trabajador (dispara la indemnización del Art. 92 LOTTT).
     */
    public function aplicaIndemnizacion(string $motivo): bool
    {
        return in_array($motivo, self::MOTIVOS_CAUSA_AJENA, true);
    }

    /**
     * Fracción de vacaciones y bono vacacional del periodo en curso (meses
     * trabajados de la fracción / 12).
     *
     * Se usa el año de servicio que se está devengando (años completados + 1),
     * de modo que la fracción incluye los días adicionales que corresponden al
     * próximo año de servicio (15 + 1 por año adicional, tope 30).
     *
     * @return array{vacaciones:float, bono:float}
     */
    public function fraccionVacacional(Empleado $empleado, CarbonInterface $egreso, int $mesesFraccion): array
    {
        $anioServicio = $this->salarioIntegral->aniosServicio($empleado, $egreso) + 1;
        $dias = $this->salarioIntegral->diasPorAnioServicio($anioServicio);

        $factor = $mesesFraccion / 12;

        return [
            'vacaciones' => round($dias['vacaciones'] * $factor, 2),
            'bono'       => round($dias['bono'] * $factor, 2),
        ];
    }

    /**
     * Utilidades fraccionadas: meses trabajados en el año calendario del egreso
     * sobre 12, por los días de bonificación del patrono.
     */
    public function fraccionUtilidades(Empleado $empleado, CarbonInterface $egreso): array
    {
        $ingreso = Carbon::instance($empleado->fecha_ingreso);
        $fin = Carbon::instance($egreso);

        $inicioAnio = Carbon::create($fin->year, 1, 1);
        $inicio = $ingreso->gt($inicioAnio) ? $ingreso : $inicioAnio;

        $meses = max(1, (int) round($inicio->diffInDays($fin) / 30));
        $meses = min(12, $meses);
        $dias = (int) ($empleado->patrono->dias_bonificacion ?? 30);

        return [
            'meses' => $meses,
            'dias'  => round($dias * $meses / 12, 2),
        ];
    }

    /**
     * Deducciones sobre prestaciones: anticipo (saldo), intereses pagados,
     * préstamos (saldo) y deducciones de ley. Los montos se convierten a la
     * moneda de la liquidación.
     *
     * @return array{anticipos:float, intereses:float, prestamos:float, de_ley:float, total:float}
     */
    public function deducciones(Empleado $empleado, ?CarbonInterface $hasta = null, ?string $moneda = null): array
    {
        $moneda = $moneda ?: $empleado->moneda;

        $movimientos = MovimientoPrestaciones::where('empleado_id', $empleado->id)
            ->where('estatus', '!=', 'anulado')
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta->toDateString()))
            ->get();

        $anticipos = 0.0;
        $prestamos = 0.0;
        foreach ($movimientos as $m) {
            if ($m->tipo === MovimientoPrestaciones::TIPO_ANTICIPO) {
                $anticipos += $this->convertir($m, $moneda);
            } elseif ($m->tipo === MovimientoPrestaciones::TIPO_PRESTAMO && $m->estatus === 'activo') {
                $prestamos += $this->convertir($m, $moneda, (float) $m->saldo);
            }
        }

        $intereses = 0.0;
        $pagosInteres = \App\Models\MovimientoPrestacionInteres::with('movimiento')
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta->toDateString()))
            ->whereHas('movimiento', function ($q) use ($empleado, $hasta) {
                $q->where('empleado_id', $empleado->id)
                    ->when($hasta, fn ($qq) => $qq->where('fecha', '<=', $hasta->toDateString()));
            })->get();

        foreach ($pagosInteres as $p) {
            $intereses += $this->convertir($p, $moneda);
        }

        return [
            'anticipos'  => round($anticipos, 2),
            'intereses'  => round($intereses, 2),
            'prestamos'  => round($prestamos, 2),
            'de_ley'     => 0.0,
            'total'      => round($anticipos + $intereses + $prestamos, 2),
        ];
    }

    /** Convierte un movimiento o pago a la moneda de la liquidación. */
    private function convertir(object $registro, string $moneda, ?float $monto = null): float
    {
        $monto = $monto ?? (float) $registro->monto;

        if ((string) $registro->moneda === $moneda) {
            return $monto;
        }

        return $this->prestaciones->convertir(
            $monto,
            (string) $registro->moneda,
            $moneda,
            $registro->fecha
        );
    }

    /**
     * Cálculo completo de la liquidación (sin persistir).
     *
     * @return array{antiguedad:array, dias_prestaciones:int, aplica_indemnizacion:bool,
     *               salario_integral_diario:float, moneda:string, tasa:?float,
     *               asignaciones:array, deducciones:array, total_asignaciones:float,
     *               total_deducciones:float, neto:float}
     */
    public function calcular(Empleado $empleado, CarbonInterface $egreso, string $motivo): array
    {
        $antiguedad = $this->antiguedad($empleado, $egreso);
        $anios = $antiguedad['anios'];
        $mesesFraccion = $antiguedad['meses'];

        $diasPrestaciones = $this->diasPrestaciones($anios, $mesesFraccion);
        $aplicaIndemnizacion = $this->aplicaIndemnizacion($motivo);

        $integral = $this->salarioIntegral->diario($empleado, $egreso);
        $salarioDiarioIntegral = (float) $integral['salario_diario_integral'];
        $salarioDiarioNormal = (float) $integral['salario_diario_normal'];
        $moneda = $integral['moneda'];
        $tasa = $integral['tasa'];

        $importeLiteralC = round($diasPrestaciones * $salarioDiarioIntegral, 2);
        $this->prestaciones->asegurarPeriodos($empleado, $egreso);
        $garantiaBruta = round($this->prestaciones->garantiaBrutaEn($empleado, $egreso), 2);
        $usaGarantia = $garantiaBruta > $importeLiteralC;
        $importePrestaciones = $usaGarantia ? $garantiaBruta : $importeLiteralC;

        $fracVac = $this->fraccionVacacional($empleado, $egreso, $mesesFraccion);
        $importeVacaciones = round($fracVac['vacaciones'] * $salarioDiarioNormal, 2);
        $importeBono = round($fracVac['bono'] * $salarioDiarioNormal, 2);

        $fracUtil = $this->fraccionUtilidades($empleado, $egreso);
        $importeUtilidades = round($fracUtil['dias'] * $salarioDiarioNormal, 2);

        $asignaciones = [
            [
                'orden'         => 10,
                'concepto'      => 'Prestaciones sociales',
                'base_legal'    => $usaGarantia
                    ? 'Art. 142 literal C LOTTT - garantía acumulada (mayor)'
                    : 'Art. 142 literal C LOTTT - 30 días por año (mayor)',
                'dias'          => $usaGarantia ? null : $diasPrestaciones,
                'salario_diario'=> $usaGarantia ? null : $salarioDiarioIntegral,
                'tipo_salario'  => $usaGarantia ? null : 'integral',
                'importe'       => $importePrestaciones,
            ],
            [
                'orden'         => 20,
                'concepto'      => 'Intereses sobre prestaciones',
                'base_legal'    => 'Art. 143 LOTTT - incluidos en la garantía comparada',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => 0.0,
            ],
        ];

        // Indemnización (Art. 92): valor equivalente a las prestaciones.
        // Se muestra sin días ni salario, justo debajo de los intereses.
        if ($aplicaIndemnizacion) {
            $asignaciones[] = [
                'orden'         => 30,
                'concepto'      => 'Indemnización por terminación',
                'base_legal'    => 'Art. 92 LOTTT - equivalente a las prestaciones sociales',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => $importePrestaciones,
            ];
        }

        $asignaciones = array_merge($asignaciones, [
            [
                'orden'         => 40,
                'concepto'      => 'Vacaciones fraccionadas',
                'base_legal'    => 'Art. 196 LOTTT',
                'dias'          => $fracVac['vacaciones'],
                'salario_diario'=> $salarioDiarioNormal,
                'tipo_salario'  => 'normal',
                'importe'       => $importeVacaciones,
            ],
            [
                'orden'         => 50,
                'concepto'      => 'Bono vacacional fraccionado',
                'base_legal'    => 'Art. 192 LOTTT',
                'dias'          => $fracVac['bono'],
                'salario_diario'=> $salarioDiarioNormal,
                'tipo_salario'  => 'normal',
                'importe'       => $importeBono,
            ],
            [
                'orden'         => 60,
                'concepto'      => 'Utilidades fraccionadas',
                'base_legal'    => 'Art. 131 LOTTT',
                'dias'          => $fracUtil['dias'],
                'salario_diario'=> $salarioDiarioNormal,
                'tipo_salario'  => 'normal',
                'importe'       => $importeUtilidades,
            ],
        ]);

        $deds = $this->deducciones($empleado, $egreso, $moneda);

        $deducciones = [
            [
                'orden'         => 10,
                'concepto'      => 'Anticipo de prestaciones',
                'base_legal'    => 'Art. 144 LOTTT',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => $deds['anticipos'],
            ],
            [
                'orden'         => 20,
                'concepto'      => 'Pago de intereses',
                'base_legal'    => 'Art. 143 LOTTT',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => $deds['intereses'],
            ],
            [
                'orden'         => 30,
                'concepto'      => 'Préstamos (saldo)',
                'base_legal'    => 'Acuerdo de préstamo sobre prestaciones',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => $deds['prestamos'],
            ],
        ];

        // Deducciones de ley: cada una en su propio renglón según flags.
        $deducciones = array_merge($deducciones, $this->deduccionesDeLey(
            $empleado->patrono,
            $importeVacaciones + $importeBono + $importeUtilidades,
            $importeUtilidades,
        ));

        $totalAsignaciones = round(array_sum(array_column($asignaciones, 'importe')), 2);
        $totalDeducciones = round(array_sum(array_column($deducciones, 'importe')), 2);
        $neto = round($totalAsignaciones - $totalDeducciones, 2);

        return [
            'antiguedad'             => $antiguedad,
            'dias_prestaciones'      => $diasPrestaciones,
            'aplica_indemnizacion'   => $aplicaIndemnizacion,
            'salario_diario_normal'  => $salarioDiarioNormal,
            'salario_integral_diario'=> $salarioDiarioIntegral,
            'moneda'                 => $moneda,
            'tasa'                   => $tasa,
            'garantia_ab'            => $garantiaBruta,
            'literal_c'              => $importeLiteralC,
            'prestaciones_mayor'     => $importePrestaciones,
            'prestaciones_origen'    => $usaGarantia ? 'garantia' : 'literal_c',
            'prestaciones_diferencia'=> round(abs($garantiaBruta - $importeLiteralC), 2),
            'asignaciones'           => $asignaciones,
            'deducciones'            => $deducciones,
            'total_asignaciones'     => $totalAsignaciones,
            'total_deducciones'      => $totalDeducciones,
            'neto'                   => $neto,
        ];
    }

    /**
     * Deducciones de ley individuales (IVSS, Paro, FAOV, INCES) según los
     * flags de configuración del patrono. Se calculan sobre la base
     * remunerativa (vacaciones + bono + utilidades fraccionadas).
     *
     * @return array<int, array>
     */
    protected function deduccionesDeLey($patrono, float $baseRemunerativa, float $baseInces): array
    {
        $deducciones = [];
        $base = abs($baseRemunerativa);

        if ($patrono->descuenta_ivss && $base > 0) {
            $deducciones[] = [
                'orden'         => 40,
                'concepto'      => 'Seguro Social (IVSS)',
                'base_legal'    => '4% sobre base remunerativa - Art. 15 Ley del Seguro Social',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => round($base * self::IVSS_PCT, 2),
            ];
        }

        if ($patrono->descuenta_paro_fijo && $base > 0) {
            $deducciones[] = [
                'orden'         => 50,
                'concepto'      => 'Régimen de Paro (SP Empleo)',
                'base_legal'    => '0,5% sobre base remunerativa - Ley Régimen Prestacional de Empleo',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => round($base * self::PARO_PCT, 2),
            ];
        }

        if ($patrono->descuenta_faov && $base > 0) {
            $deducciones[] = [
                'orden'         => 60,
                'concepto'      => 'FAOV',
                'base_legal'    => '1% sobre base remunerativa - Ley Régimen Prestacional de Vivienda y Hábitat',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => round($base * self::FAOV_PCT, 2),
            ];
        }

        $empleadosActivos = $patrono->empleados()
            ->where('estatus', 'Activo')
            ->count();

        if ($patrono->descuenta_inces && $empleadosActivos >= 5 && abs($baseInces) > 0) {
            $deducciones[] = [
                'orden'         => 70,
                'concepto'      => 'INCES',
                'base_legal'    => '0,5% sobre utilidades - Ley del INCES, 5 o más trabajadores',
                'dias'          => null,
                'salario_diario'=> null,
                'tipo_salario'  => null,
                'importe'       => round(abs($baseInces) * self::INCES_PCT, 2),
            ];
        }

        return $deducciones;
    }

    /**
     * Persiste la liquidación con sus conceptos.
     */
    public function crear(Empleado $empleado, CarbonInterface $egreso, string $motivo, ?string $observacion = null): Liquidacion
    {
        $existe = Liquidacion::where('empleado_id', $empleado->id)
            ->whereIn('estatus', ['por_aprobar', 'aprobada'])
            ->exists();

        if ($existe) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'empleado_id' => 'El trabajador ya tiene una liquidación vigente (por aprobar o aprobada).',
            ]);
        }

        $datos = $this->calcular($empleado, $egreso, $motivo);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($empleado, $egreso, $motivo, $observacion, $datos) {
            $liquidacion = Liquidacion::create([
                'patrono_id'               => $empleado->patrono_id,
                'empleado_id'              => $empleado->id,
                'fecha_liquidacion'        => $egreso->toDateString(),
                'fecha_ingreso'            => $empleado->fecha_ingreso,
                'fecha_egreso'             => $egreso->toDateString(),
                'motivo'                   => $motivo,
                'observacion'              => $observacion,
                'anios_servicio'           => $datos['antiguedad']['anios'],
                'meses_fraccion'           => $datos['antiguedad']['meses'],
                'dias_prestaciones'        => $datos['dias_prestaciones'],
                'dias_indemnizacion'       => $datos['aplica_indemnizacion'] ? 1 : 0,
                'garantia_ab'              => $datos['garantia_ab'],
                'literal_c'                => $datos['literal_c'],
                'prestaciones_mayor'       => $datos['prestaciones_mayor'],
                'prestaciones_origen'      => $datos['prestaciones_origen'],
                'prestaciones_diferencia'  => $datos['prestaciones_diferencia'],
                'salario_diario_normal'    => $datos['salario_diario_normal'],
                'salario_integral_diario'  => $datos['salario_integral_diario'],
                'moneda'                   => $datos['moneda'],
                'tasa_cambio'              => $datos['tasa'],
                'total_asignaciones'       => $datos['total_asignaciones'],
                'total_deducciones'        => $datos['total_deducciones'],
                'neto'                     => $datos['neto'],
                'estatus'                  => 'por_aprobar',
            ]);

            foreach ($datos['asignaciones'] as $concepto) {
                $liquidacion->conceptos()->create([
                    'tipo'      => 'asignacion',
                    'orden'     => $concepto['orden'],
                    'concepto'  => $concepto['concepto'],
                    'base_legal'=> $concepto['base_legal'],
                    'dias'      => $concepto['dias'],
                    'salario_diario' => $concepto['salario_diario'],
                    'tipo_salario' => $concepto['tipo_salario'] ?? null,
                    'importe'   => $concepto['importe'],
                    'editable'  => true,
                ]);
            }

            foreach ($datos['deducciones'] as $concepto) {
                $liquidacion->conceptos()->create([
                    'tipo'      => 'deduccion',
                    'orden'     => $concepto['orden'],
                    'concepto'  => $concepto['concepto'],
                    'base_legal'=> $concepto['base_legal'],
                    'dias'      => $concepto['dias'],
                    'salario_diario' => $concepto['salario_diario'],
                    'tipo_salario' => $concepto['tipo_salario'] ?? null,
                    'importe'   => $concepto['importe'],
                    'editable'  => true,
                ]);
            }

            // El trabajador se marca inactivo al aprobar la liquidación, no al
            // crearla; así una liquidación pendiente o anulada no bloquea la nómina.
            return $liquidacion;
        });
    }

    /**
     * Regenera los conceptos y totales de una liquidación a partir del
     * cálculo vigente (empleado + fecha de egreso + motivo). Útil para
     * reparar liquidaciones creadas antes de que existieran los campos
     * salario_diario / tipo_salario. Conserva los totales recalculados.
     */
    public function recalcular(Liquidacion $liquidacion): Liquidacion
    {
        $empleado = $liquidacion->empleado;
        $datos = $this->calcular($empleado, $liquidacion->fecha_egreso, $liquidacion->motivo);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($liquidacion, $empleado, $datos) {
            $liquidacion->conceptos()->delete();

            foreach ($datos['asignaciones'] as $concepto) {
                $liquidacion->conceptos()->create([
                    'tipo'      => 'asignacion',
                    'orden'     => $concepto['orden'],
                    'concepto'  => $concepto['concepto'],
                    'base_legal'=> $concepto['base_legal'],
                    'dias'      => $concepto['dias'],
                    'salario_diario' => $concepto['salario_diario'],
                    'tipo_salario' => $concepto['tipo_salario'] ?? null,
                    'importe'   => $concepto['importe'],
                    'editable'  => true,
                ]);
            }

            foreach ($datos['deducciones'] as $concepto) {
                $liquidacion->conceptos()->create([
                    'tipo'      => 'deduccion',
                    'orden'     => $concepto['orden'],
                    'concepto'  => $concepto['concepto'],
                    'base_legal'=> $concepto['base_legal'],
                    'dias'      => $concepto['dias'],
                    'salario_diario' => $concepto['salario_diario'],
                    'tipo_salario' => $concepto['tipo_salario'] ?? null,
                    'importe'   => $concepto['importe'],
                    'editable'  => true,
                ]);
            }

            $liquidacion->update([
                'anios_servicio'           => $datos['antiguedad']['anios'],
                'meses_fraccion'           => $datos['antiguedad']['meses'],
                'dias_prestaciones'        => $datos['dias_prestaciones'],
                'dias_indemnizacion'       => $datos['aplica_indemnizacion'] ? 1 : 0,
                'garantia_ab'              => $datos['garantia_ab'],
                'literal_c'                => $datos['literal_c'],
                'prestaciones_mayor'       => $datos['prestaciones_mayor'],
                'prestaciones_origen'      => $datos['prestaciones_origen'],
                'prestaciones_diferencia'  => $datos['prestaciones_diferencia'],
                'salario_diario_normal'    => $datos['salario_diario_normal'],
                'salario_integral_diario'  => $datos['salario_integral_diario'],
                'total_asignaciones'       => $datos['total_asignaciones'],
                'total_deducciones'        => $datos['total_deducciones'],
                'neto'                     => $datos['neto'],
            ]);

            return $liquidacion->fresh();
        });
    }

    /**
     * Actualiza importes editados y recalculca totales.
     */
    public function actualizarConceptos(Liquidacion $liquidacion, array $conceptos): Liquidacion
    {
        foreach ($conceptos as $id => $dato) {
            $concepto = $liquidacion->conceptos()->find((int) $id);
            if (! $concepto) {
                continue;
            }

            $concepto->update([
                'dias'    => ($dato['dias'] ?? null) !== '' && ($dato['dias'] ?? null) !== null ? (float) $dato['dias'] : null,
                'salario_diario' => ($dato['salario_diario'] ?? null) !== '' && ($dato['salario_diario'] ?? null) !== null
                    ? (float) $dato['salario_diario']
                    : null,
                'importe' => (float) ($dato['importe'] ?? 0),
            ]);
        }

        // Sincronizar dias_prestaciones del header con el concepto Prestaciones sociales
        $conceptoPrestaciones = $liquidacion->conceptos()
            ->where('concepto', 'Prestaciones sociales')
            ->first();
        if ($conceptoPrestaciones) {
            $liquidacion->update([
                'dias_prestaciones' => (int) ($conceptoPrestaciones->dias ?? 0),
            ]);
        }

        $totalAsignaciones = round((float) $liquidacion->asignaciones()->sum('importe'), 2);
        $totalDeducciones = round((float) $liquidacion->deducciones()->sum('importe'), 2);

        $liquidacion->update([
            'total_asignaciones' => $totalAsignaciones,
            'total_deducciones'  => $totalDeducciones,
            'neto'               => round($totalAsignaciones - $totalDeducciones, 2),
        ]);

        return $liquidacion->fresh();
    }
}
