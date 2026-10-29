<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BonificacionFinAno;
use App\Models\Empleado;
use App\Models\Parametro;
use App\Models\Patrono;
use Carbon\Carbon;

/**
 * Utilidades y Bonificación de Fin de Año (anticipo de utilidades).
 *
 * El período de cálculo es el año natural (01/01/X — 31/12/X). El mes actual
 * y diciembre se asumen como laborados por completo. Para trabajadores que
 * ingresaron durante el año, los meses laborados se calculan desde su fecha de
 * ingreso (el mes de ingreso completa) hasta diciembre, sin truncar por "hoy".
 *
 * El bono se determina como fracción del tope de días:
 *   dias_a_pagar = dias_base × (meses_laborados / 12)   [límite superior 120]
 *   bonificacion = salario_base × (dias_a_pagar / 30)
 */
class UtilidadesService
{
    public const INCES_PCT = 0.005; // Aporte del trabajador al INCES — 0,5%

    public const DIAS_MES = 30;

    public const DIAS_MAXIMO = 120;

    public function __construct(
        private SueldosService $sueldos = new SueldosService,
    ) {}

    /**
     * Periodo de cálculo de un año: 01/01/X — 31/12/X.
     *
     * @return array{inicio:Carbon, fin:Carbon}
     */
    public function periodoDeAnio(int $anio): array
    {
        return [
            'inicio' => Carbon::create($anio, 1, 1),
            'fin' => Carbon::create($anio, 12, 31),
        ];
    }

    /**
     * Meses laborados de un empleado en el año: desde su fecha de ingreso
     * (el mes del ingreso completa si ingresó el día 1; el siguiente si es
     * posterior) hasta diciembre, contándolo completo. Tope 12; 0 si no
     * alcanzó a laborar dentro del año.
     */
    public function mesesLaborados(Empleado $empleado, int $anio): float
    {
        $ingreso = $empleado->fecha_ingreso;
        $inicioAnio = Carbon::create($anio, 1, 1);

        // Ingreso previo (o exactamente el 1 de enero) — año completo.
        if ($ingreso->lte($inicioAnio)) {
            return 12.0;
        }

        // Ingreso dentro del año.
        if ($ingreso->year > $anio) {
            return 0.0;
        }

        $mesInicio = $ingreso->day === 1
            ? $ingreso->month
            : $ingreso->month + 1; // se descarta el mes de ingreso parcial.

        if ($mesInicio > 12) {
            return 0.0;
        }

        return 12.0 - $mesInicio + 1;
    }

    /**
     * Días de bonificación a pagar (fracción, máx 120).
     */
    public function diasAPagar(int $diasBase, float $meses): float
    {
        return round(min($diasBase * ($meses / 12), self::DIAS_MAXIMO), 2);
    }

    /**
     * Salario base mensual del empleado (moneda propia), como [usd, bs].
     *
     * - 'ultimo'   : último sueldo registrado/determinado (ficha o cambio).
     * - 'promedio' : promedio de los sueldos de los meses laborados del año.
     */
    public function salarioBase(Empleado $empleado, int $anio, string $tipo): array
    {
        $sueldo = $empleado->sueldos()->first();

        if (! $sueldo) {
            // Sin historial: se calcula/consulta el sueldo actual.
            $dato = $this->sueldos->sueldoEn($empleado, now());

            return [
                'usd' => $dato['monto_usd'] !== null ? (float) $dato['monto_usd'] : null,
                'bs' => $dato['monto_bs'] !== null ? (float) $dato['monto_bs'] : null,
            ];
        }

        if ($tipo === 'promedio') {
            return $this->promedioSueldo($empleado, $anio);
        }

        // 'ultimo': último sueldo registrado.
        return [
            'usd' => $sueldo->monto_usd !== null ? (float) $sueldo->monto_usd : null,
            'bs' => $sueldo->monto_bs !== null ? (float) $sueldo->monto_bs : null,
        ];
    }

    /**
     * Promedio USD/BS de los sueldos de los meses que el empleado haya
     * laborado dentro del año.
     */
    private function promedioSueldo(Empleado $empleado, int $anio): array
    {
        $desde = Carbon::create($anio, 1, 1)->format('Y-m');
        $hasta = Carbon::create($anio, 12, 1)->format('Y-m');

        $rows = $empleado->sueldos()
            ->whereBetween('periodo', [$desde, $hasta])
            ->get();

        if ($rows->isEmpty()) {
            $dato = $this->sueldos->sueldoEn($empleado, now());

            return [
                'usd' => $dato['monto_usd'] !== null ? (float) $dato['monto_usd'] : null,
                'bs' => $dato['monto_bs'] !== null ? (float) $dato['monto_bs'] : null,
            ];
        }

        $usd = array_sum($rows->pluck('monto_usd')->filter()->all());
        $bs = array_sum($rows->pluck('monto_bs')->filter()->all());
        $n = $rows->count();

        return [
            'usd' => $n ? round($usd / $n, 2) : null,
            'bs' => $n ? round($bs / $n, 2) : null,
        ];
    }

    /**
     * Calcula y guarda (update-or-insert) la bonificación de un año para un
     * empleado. Devuelve el modelo creado/actualizado.
     */
    public function calcularBonificacion(Empleado $empleado, int $anio, int $diasBase, string $tipoBase = 'ultimo'): BonificacionFinAno
    {
        $patrono = $empleado->patrono;
        $periodo = $this->periodoDeAnio($anio);
        $meses = $this->mesesLaborados($empleado, $anio);
        $dias = $this->diasAPagar($diasBase, $meses);

        $salario = $this->salarioBase($empleado, $anio, $tipoBase);

        $salarioDiarioUsd = $salario['usd'] !== null ? round($salario['usd'] / self::DIAS_MES, 2) : null;
        $salarioDiarioBs = $salario['bs'] !== null ? round($salario['bs'] / self::DIAS_MES, 2) : null;

        // Tasa BCV del día del cálculo (o la fecha anterior más reciente si hoy no emitió)
        // con respaldo al parámetro global.
        $tasaBcv = (new TasasBcvService)->tasaEn(Carbon::now());
        $tasa = $tasaBcv ?? (float) Parametro::get('tasa_dolar', 0.0);

        // Moneda desde la fuente centralizada (sueldos), con respaldo a la ficha.
        $vigente = $this->sueldos->sueldoVigente($empleado, Carbon::now());
        $moneda = $vigente['moneda'];

        // Bono en la moneda del empleado y equivalente en la otra.
        if ($moneda === 'USD') {
            $bonoUsd = round(($salario['usd'] ?? 0) * ($dias / self::DIAS_MES), 2);
            $bonoBs = $tasa > 0 ? round($bonoUsd * $tasa, 2) : null;
        } else {
            $bonoBs = round(($salario['bs'] ?? 0) * ($dias / self::DIAS_MES), 2);
            $bonoUsd = $tasa > 0 ? round($bonoBs / $tasa, 2) : null;
        }

        $totalAsignaciones = $moneda === 'USD' ? $bonoUsd : $bonoBs;
        $deducciones = $this->deduccionesLegales($patrono, $totalAsignaciones);
        $totalDeducciones = round(array_sum(array_column($deducciones, 'importe')), 2);
        $neto = round($totalAsignaciones - $totalDeducciones, 2);

        $asignaciones = [[
            'concepto' => 'Bonificación de Fin de Año',
            'base_legal' => 'Art. 132 LOTTT - anticipo de utilidades',
            'dias' => $dias,
            'importe' => $totalAsignaciones,
            'observacion' => "{$meses} mes(es) laborados — {$dias} días de bono",
        ]];

        return BonificacionFinAno::updateOrCreate(
            ['empleado_id' => $empleado->id, 'anio' => $anio],
            [
                'patrono_id' => $patrono->id,
                'periodo_inicio' => $periodo['inicio']->toDateString(),
                'periodo_fin' => $periodo['fin']->toDateString(),
                'meses_laborados' => $meses,
                'dias_base' => $diasBase,
                'dias_a_pagar' => $dias,
                'tipo_base' => $tipoBase,
                'salario_mensual_usd' => $salario['usd'] ?? null,
                'salario_mensual_bs' => $salario['bs'] ?? null,
                'salario_diario_usd' => $salarioDiarioUsd,
                'salario_diario_bs' => $salarioDiarioBs,
                'bono_usd' => $bonoUsd,
                'bono_bs' => $bonoBs,
                'tasa_cambio' => $tasa,
                'asignaciones' => $asignaciones,
                'deducciones' => $deducciones,
                'total_asignaciones' => $totalAsignaciones,
                'total_deducciones' => $totalDeducciones,
                'neto' => $neto,
                'moneda' => $moneda,
                'estatus' => 'calculado',
            ]
        );
    }

    /**
     * Deducciones legales de la bonificación de fin de año.
     *
     * Solo aplica el aporte del trabajador al INCES (0,5%) sobre el total,
     * y únicamente si el patrono tiene marcado el flag y ocupa 5 o más
     * trabajadores. IVSS, FAOV y Paro Forzoso NO se descuentan en este
     * beneficio por estar excluido del salario normal de cálculo mensual.
     */
    private function deduccionesLegales(Patrono $patrono, float $base): array
    {
        $deducciones = [];
        $base = abs($base);

        $empleadosActivos = $patrono->empleados()
            ->where('estatus', 'Activo')
            ->count();

        if ($patrono->descuenta_inces && $empleadosActivos >= 5) {
            $deducciones[] = [
                'concepto' => 'INCES (Ley INCES)',
                'base_legal' => '0,5% sobre la bonificación (5 o más trabajadores)',
                'porcentaje' => self::INCES_PCT,
                'importe' => round($base * self::INCES_PCT, 2),
            ];
        }

        return $deducciones;
    }

    /**
     * Emite el recibo (fecha_emision + estado aprobado) para un cálculo ya calculado.
     * Captura la tasa BCV del día de emisión y la guarda como referencia.
     */
    public function emitirRecibo(BonificacionFinAno $bonificacion): BonificacionFinAno
    {
        if ($bonificacion->estatus !== 'aprobado') {
            $tasaBcv = (new TasasBcvService)->tasaEn(Carbon::now());
            $tasa = $tasaBcv ?? (float) Parametro::get('tasa_dolar', 0.0);

            $bonificacion->update([
                'estatus' => 'aprobado',
                'fecha_aprobacion' => now()->toDateString(),
                'fecha_emision' => now()->toDateString(),
                'tasa_cambio' => $tasa,
            ]);
        }

        return $bonificacion->fresh();
    }

    /**
     * Aprueba en bloque todas las bonificaciones pendientes del patrono,
     * opcionalmente limitadas a un año. Devuelve la cantidad aprobada.
     */
    public function aprobarPendientes(Patrono $patrono, ?int $anio = null): int
    {
        $tasaBcv = (new TasasBcvService)->tasaEn(Carbon::now());
        $tasa = $tasaBcv ?? (float) Parametro::get('tasa_dolar', 0.0);

        $pendientes = BonificacionFinAno::where('patrono_id', $patrono->id)
            ->when($anio !== null, fn ($query) => $query->where('anio', $anio))
            ->where('estatus', 'calculado')
            ->get();

        // Se actualiza modelo por modelo para que cada aprobación deje su
        // sello de usuario y su registro en el historial de estados.
        foreach ($pendientes as $bonificacion) {
            $bonificacion->update([
                'estatus' => 'aprobado',
                'fecha_aprobacion' => now()->toDateString(),
                'fecha_emision' => now()->toDateString(),
                'tasa_cambio' => $tasa,
            ]);
        }

        return $pendientes->count();
    }

    /**
     * Formatea un monto en la moneda del empleado con el equivalente en Bs.
     *
     * El equivalente se muestra únicamente si el patrono tiene activado
     * `mostrar_equivalencia_usd` y hay una tasa > 0 de referencia (la de la
     * emisión del recibo, o la del día si esta no existe).
     */
    public function formatearMonto(Empleado $empleado, float $monto, ?float $tasa = null, ?BonificacionFinAno $bonificacion = null, ?string $moneda = null): array
    {
        $mostrar = $bonificacion && $bonificacion->patrono
            ? (bool) $bonificacion->patrono->mostrar_equivalencia_usd
            : (bool) ($empleado->patrono->mostrar_equivalencia_usd ?? false);

        // Moneda desde la fuente (sueldos), con respaldo a la bonificación y la ficha.
        $moneda = $moneda
            ?? ($bonificacion?->moneda)
            ?? $empleado->moneda;

        if ($moneda === 'USD') {
            $tasa = $tasa ?? $this->tasaDia();

            return [
                'moneda' => 'USD',
                'principal' => 'USD '.formato_monto($monto),
                'equivalente' => ($tasa > 0 && $mostrar) ? '≈ Bs. '.formato_monto($monto * $tasa) : null,
            ];
        }

        return [
            'moneda' => 'BS',
            'principal' => 'Bs. '.formato_monto($monto),
            'equivalente' => null,
        ];
    }

    /**
     * Tasa BCV del día (o la más reciente emitida antes), con respaldo al
     * parámetro global.
     */
    private function tasaDia(): float
    {
        $tasaBcv = (new TasasBcvService)->tasaEn(Carbon::now());

        return $tasaBcv ?? (float) Parametro::get('tasa_dolar', 0.0);
    }

    /**
     * Años disponibles para el filtro (desde el primer registro del patrono
     * hasta el año vigente, garantizando siempre este último).
     */
    public function aniosDisponibles(int $patronoId): array
    {
        $vigente = (int) Carbon::now()->year;
        $anios = BonificacionFinAno::where('patrono_id', $patronoId)
            ->pluck('anio')
            ->map(fn ($a) => (int) $a)
            ->push($vigente)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $anios;
    }
}
