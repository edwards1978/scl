<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\Sueldo;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Servicio de sueldos mensuales.
 *
 * La tabla `sueldos` guarda el sueldo de cada mes (periodo YYYY-MM) con su
 * equivalente en USD y Bs y la tasa del BCV aplicada. Se alimenta desde:
 * - la ficha del empleado (sueldo inicial, si no hay cambios), o
 * - la tabla `cambios_salarios` (variaciones vigentes por fecha).
 *
 * El cálculo se hace SIEMPRE a mes cerrado: al estar en el día 15 de junio
 * de 2026 se calcula el periodo 2026-06 completo (cierre 30/06/2026).
 */
class SueldosService
{
    public function __construct(
        private TasasBcvService $tasas = new TasasBcvService,
    ) {}

    /**
     * Periodo YYYY-MM de una fecha.
     */
    public function periodoDe(CarbonInterface $fecha): string
    {
        return $fecha->format('Y-m');
    }

    /**
     * Último día del mes al que pertenece una fecha (cierre de periodo).
     */
    public function cierreDe(CarbonInterface $fecha): Carbon
    {
        return Carbon::instance($fecha)->endOfMonth();
    }

    /**
     * Sueldo vigente para una fecha, tomado de la tabla `sueldos` (fuente
     * centralizada). Verifica de forma ligera que los periodos estén generados:
     * si el último sueldo registrado es anterior al periodo requerido (topado
     * al mes actual), dispara la generación de los periodos faltantes y luego
     * devuelve el más reciente <= fecha.
     *
     * @return array{monto:float, moneda:string, origen:string}
     */
    public function sueldoVigente(Empleado $empleado, CarbonInterface $fecha): array
    {
        // Se topea al mes actual: no se generan periodos futuros.
        $periodoRequerido = min(
            $fecha->format('Y-m'),
            now()->format('Y-m')
        );

        $ultimo = Sueldo::where('empleado_id', $empleado->id)
            ->orderByDesc('periodo')
            ->first();

        if (! $ultimo || $ultimo->periodo < $periodoRequerido) {
            $this->asegurarSueldos($empleado, Carbon::createFromFormat('Y-m', $periodoRequerido));
        }

        $sueldo = Sueldo::where('empleado_id', $empleado->id)
            ->where('periodo', '<=', $periodoRequerido)
            ->orderByDesc('periodo')
            ->first();

        if (! $sueldo) {
            // Sin sueldos generados: respaldo a la ficha/cambios sin recursión.
            return $this->sueldoDesdeFuente($empleado, $fecha);
        }

        return [
            'monto' => $sueldo->moneda_sueldo === 'USD' ? (float) $sueldo->monto_usd : (float) $sueldo->monto_bs,
            'moneda' => $sueldo->moneda_sueldo,
            'origen' => 'sueldos',
        ];
    }

    /**
     * Sueldo desde la fuente primaria (cambio salarial o ficha), sin pasar por
     * la tabla `sueldos`. Lo usa exclusivamente `calcularMes()` para construir
     * los periodos, evitando recursión con `sueldoVigente()`.
     *
     * @return array{monto:float, moneda:string, origen:string}
     */
    private function sueldoDesdeFuente(Empleado $empleado, CarbonInterface $fecha): array
    {
        $cambio = CambioSalario::where('empleado_id', $empleado->id)
            ->where('fecha_cambio', '<=', $fecha->toDateString())
            ->orderByDesc('fecha_cambio')
            ->first();

        if ($cambio) {
            return [
                'monto' => (float) $cambio->monto,
                'moneda' => $cambio->moneda,
                'origen' => 'cambio',
            ];
        }

        // Sin cambio vigente: se usa el sueldo inicial de la ficha.
        return [
            'monto' => (float) ($empleado->salario_inicial ?? $empleado->salario_base),
            'moneda' => $empleado->moneda,
            'origen' => 'ficha',
        ];
    }

    /**
     * Calcula los valores de un periodo (mes) completo.
     *
     * @return array{periodo:string, cierre:Carbon, moneda_sueldo:string,
     *               monto_usd:?float, monto_bs:?float, tasa_bcv:?float, origen:string}
     */
    public function calcularMes(Empleado $empleado, CarbonInterface $fechaDelMes): array
    {
        $cierre = $this->cierreDe($fechaDelMes);
        $vigente = $this->sueldoDesdeFuente($empleado, $cierre);
        $tasa = $this->tasas->tasaEn($cierre);

        $montoUsd = null;
        $montoBs = null;

        if ($vigente['moneda'] === 'USD') {
            $montoUsd = $vigente['monto'];
            $montoBs = $tasa !== null ? round($vigente['monto'] * $tasa, 2) : null;
        } else {
            $montoBs = $vigente['monto'];
            $montoUsd = $tasa !== null && $tasa > 0 ? round($vigente['monto'] / $tasa, 2) : null;
        }

        return [
            'periodo' => $this->periodoDe($fechaDelMes),
            'cierre' => $cierre,
            'moneda_sueldo' => $vigente['moneda'],
            'monto_usd' => $montoUsd,
            'monto_bs' => $montoBs,
            'tasa_bcv' => $tasa,
            'origen' => $vigente['origen'],
        ];
    }

    /**
     * Registra un periodo calculado (update-or-insert por empleado+periodo).
     */
    public function registrarMes(Empleado $empleado, CarbonInterface $fechaDelMes): Sueldo
    {
        $data = $this->calcularMes($empleado, $fechaDelMes);

        return Sueldo::updateOrCreate(
            [
                'empleado_id' => $empleado->id,
                'periodo' => $data['periodo'],
            ],
            [
                'fecha_cierre' => $data['cierre']->toDateString(),
                'moneda_sueldo' => $data['moneda_sueldo'],
                'monto_usd' => $data['monto_usd'],
                'monto_bs' => $data['monto_bs'],
                'tasa_bcv' => $data['tasa_bcv'],
                'origen' => $data['origen'],
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
     * Verifica y registra los sueldos pendientes hasta el mes actual
     * (a mes cerrado). Solo crea los periodos faltantes.
     *
     * @return array{creados:int, existentes:int}
     */
    public function asegurarSueldos(Empleado $empleado, ?CarbonInterface $hasta = null): array
    {
        $hasta = Carbon::instance($hasta ?: now());
        $requeridos = $this->periodosRequeridos($empleado, $hasta);

        if (empty($requeridos)) {
            return ['creados' => 0, 'existentes' => 0];
        }

        $existentes = Sueldo::where('empleado_id', $empleado->id)
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
     * Regenera los sueldos de un empleado desde un periodo (o todos) hacia
     * adelante hasta el mes actual. Primero elimina los periodos afectados
     * para reflejar los cambios de sueldo aplicados desde esa fecha.
     */
    public function regenerarSueldos(Empleado $empleado, ?string $desdePeriodo = null): int
    {
        $desde = $desdePeriodo
            ? Carbon::createFromFormat('Y-m', $desdePeriodo)
            : Carbon::instance($empleado->fecha_ingreso)->startOfMonth();

        $desde->startOfMonth();
        $hasta = now();

        $eliminados = Sueldo::where('empleado_id', $empleado->id)
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
     * Consulta el sueldo registrado para la fecha dada (periodo del mes).
     * Si el periodo aún no está calculado, se calcula al vuelo y se registra.
     *
     * @return array{periodo:string, monto_usd:?float, monto_bs:?float,
     *               tasa_bcv:?float, moneda_sueldo:string, origen:string}
     */
    public function sueldoEn(Empleado $empleado, CarbonInterface $fecha): array
    {
        $periodo = $this->periodoDe($fecha);

        $sueldo = Sueldo::where('empleado_id', $empleado->id)
            ->where('periodo', $periodo)
            ->first();

        if (! $sueldo) {
            $data = $this->calcularMes($empleado, $fecha);
            $sueldo = $this->registrarMes($empleado, $fecha);
            $data['periodo'] = $periodo;
            $data['origen'] = $sueldo->origen;

            return $data;
        }

        return [
            'periodo' => $sueldo->periodo,
            'monto_usd' => $sueldo->monto_usd !== null ? (float) $sueldo->monto_usd : null,
            'monto_bs' => $sueldo->monto_bs !== null ? (float) $sueldo->monto_bs : null,
            'tasa_bcv' => $sueldo->tasa_bcv !== null ? (float) $sueldo->tasa_bcv : null,
            'moneda_sueldo' => $sueldo->moneda_sueldo,
            'origen' => $sueldo->origen,
        ];
    }

    /**
     * Sueldo en la moneda del empleado (o USD si se pide), para el período
     * completo calculado. Utilidad para otros cálculos laborales.
     */
    public function montoSueldoEn(Empleado $empleado, CarbonInterface $fecha): float
    {
        $dato = $this->sueldoEn($empleado, $fecha);

        return (float) ($dato['moneda_sueldo'] === 'USD' ? $dato['monto_usd'] : $dato['monto_bs']);
    }

    /**
     * Verifica de forma general si existen tasas del BCV para calcular los
     * sueldos de todos los meses requeridos por el sistema.
     *
     * Un mes requiere tasa en su cierre (último día). La tasa usada es la
     * última publicada en o antes de ese cierre; si no existe ninguna, el
     * mes queda sin monto en la otra moneda y se reporta como faltante.
     *
     * @return array{periodos:int, cubiertos:int, faltantes:array<int,array{periodo:string,cierre:string}>}
     */
    public function verificarTasasFaltantes(): array
    {
        $primerIngreso = Empleado::query()->min('fecha_ingreso');

        if (! $primerIngreso) {
            return ['periodos' => 0, 'cubiertos' => 0, 'faltantes' => []];
        }

        $inicio = Carbon::parse($primerIngreso)->startOfMonth();
        $fin = now()->startOfMonth();

        $periodos = 0;
        $cubiertos = 0;
        $faltantes = [];

        $cursor = $inicio->copy();
        while ($cursor->lte($fin)) {
            $cierre = $cursor->copy()->endOfMonth();
            $periodos++;

            if ($this->tasas->tasaEn($cierre) !== null) {
                $cubiertos++;
            } else {
                $faltantes[] = [
                    'periodo' => $cursor->format('Y-m'),
                    'cierre' => $cierre->toDateString(),
                ];
            }

            $cursor->addMonth();
        }

        return compact('periodos', 'cubiertos', 'faltantes');
    }

    /**
     * Sincroniza la ficha del empleado con el sueldo vigente:
     * - Si existe un cambio salarial en o antes de hoy, salario_base refleja
     *   el más reciente de ellos.
     * - Si NO existe ningún cambio (por ejemplo tras eliminar el último),
     *   restaura el sueldo inicial de la ficha.
     */
    public function sincronizarFicha(Empleado $empleado): Empleado
    {
        $cambio = CambioSalario::where('empleado_id', $empleado->id)
            ->where('fecha_cambio', '<=', now()->toDateString())
            ->orderByDesc('fecha_cambio')
            ->first();

        if ($cambio) {
            $empleado->update([
                'salario_base' => (float) $cambio->monto,
                'moneda' => $cambio->moneda,
            ]);
        } elseif ($empleado->salario_inicial !== null) {
            $empleado->update([
                'salario_base' => (float) $empleado->salario_inicial,
            ]);
        }

        return $empleado->fresh();
    }
}
