<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use App\Models\MovimientoPrestaciones;
use App\Models\Patrono;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de anticipos y préstamos sobre prestaciones sociales.
 *
 * Un anticipo (Art. 144 LOTTT) se concede sobre el saldo de prestaciones y
 * admite únicamente motivos de vivienda, educación o salud. El porcentaje del
 * saldo disponible depende del sistema de depósito configurado en el patrono
 * (contabilidad, fideicomiso o fondo). La cantidad de anticipos por año está
 * limitada por la configuración del patrono (anticipos_max_anio).
 *
 * Los préstamos son un mecanismo de la misma naturaleza (adelanto contra el
 * saldo de prestaciones) pero con plan de amortización (plazo, interés y cuota).
 */
class MovimientosPrestacionesService
{
    public function __construct(
        private TasasBcvService $tasas = new TasasBcvService,
        private PrestacionesSocialesService $prestaciones = new PrestacionesSocialesService,
    ) {}

    /**
     * Total acumulado (Bs o USD, en moneda de origen) otorgado al empleado.
     */
    public function totalOtorgado(Empleado $empleado, ?CarbonInterface $hasta = null): float
    {
        return (float) MovimientoPrestaciones::where('empleado_id', $empleado->id)
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta->toDateString()))
            ->where('estatus', '!=', 'anulado')
            ->sum('monto');
    }

    /**
     * Anticipos del año calendario (contabiliza solo tipo anticipo activos).
     */
    public function anticiposEnAnio(Empleado $empleado, ?CarbonInterface $fecha = null): Collection
    {
        $fecha = $fecha ?: now();

        return MovimientoPrestaciones::where('empleado_id', $empleado->id)
            ->where('tipo', MovimientoPrestaciones::TIPO_ANTICIPO)
            ->where('estatus', '!=', 'anulado')
            ->whereYear('fecha', $fecha->year)
            ->get();
    }

    public function anticiposRestantesEnAnio(Empleado $empleado, ?CarbonInterface $fecha = null): int
    {
        $max = $empleado->patrono->anticipos_max_anio ?? 2;

        return max(0, $max - $this->anticiposEnAnio($empleado, $fecha)->count());
    }

    /**
     * Porcentaje aplicable del saldo según el sistema de depósito del patrono.
     */
    public function pctAnticipo(Patrono $patrono): int
    {
        return match ($patrono->deposito_tipo) {
            'fideicomiso' => (int) $patrono->anticipos_pct_fideicomiso,
            'fondo' => (int) $patrono->anticipos_pct_fondo,
            default => (int) $patrono->anticipos_pct_contabilidad,
        };
    }

    /**
     * Saldo de la garantía (capital, sin interés pendiente) a una fecha.
     * Los anticipos ya vienen descontados; los préstamos son deuda separada.
     *
     * @return array{anios:int, dias:int, salario_integral_diario:float,
     *               moneda:string, tasa:?float, saldo:float, es_proyeccion:bool}
     */
    public function saldoPrestaciones(Empleado $empleado, ?CarbonInterface $fecha = null): array
    {
        $fecha = $fecha ?: now();
        $integral = app(SalarioIntegralService::class)->diario($empleado, $fecha);
        $anios = app(SalarioIntegralService::class)->aniosServicio($empleado, $fecha);
        // Solo lectura: los períodos se generan en la operación que registra
        // el anticipo, nunca en una consulta GET. Se incluyen las proyecciones
        // para que una fecha futura use el saldo proyectado como base operativa.
        $registro = $this->prestaciones->registroEn($empleado, $fecha, true);
        $capital = $registro
            ? (float) ($registro->capital_final ?? $registro->saldo_final)
            : 0.0;

        $dias = $integral['salario_diario_integral'] > 0
            ? (int) round($capital / $integral['salario_diario_integral'])
            : 0;

        return [
            'anios' => $anios,
            'dias' => $dias,
            'salario_integral_diario' => $integral['salario_diario_integral'],
            'moneda' => $integral['moneda'],
            'tasa' => $integral['tasa'],
            'saldo' => round($capital, 2),
            'es_proyeccion' => (bool) $registro?->es_proyeccion,
        ];
    }

    /**
     * Monto máximo de anticipo permitido: porcentaje del saldo de prestaciones.
     */
    public function maximoAnticipo(Empleado $empleado, ?CarbonInterface $fecha = null): array
    {
        $saldo = $this->saldoPrestaciones($empleado, $fecha);
        $pct = $this->pctAnticipo($empleado->patrono);
        $max = $saldo['saldo'] * $pct / 100;

        return [
            ...$saldo,
            'pct' => $pct,
            'maximo' => round($max, 2),
        ];
    }

    /**
     * Registra un anticipo o préstamo. Devuelve el modelo creado o lanza una
     * excepción de validación de negocio (ValidationException).
     *
     * @param  array  $data  datos validados (sin patrono_id/empleado_id)
     */
    public function crear(Empleado $empleado, array $data): MovimientoPrestaciones
    {
        $tipo = $data['tipo'];
        $moneda = $data['moneda'];
        $monto = (float) $data['monto'];
        $fecha = $data['fecha'] instanceof CarbonInterface
            ? $data['fecha']
            : Carbon::parse($data['fecha']);

        return DB::transaction(function () use ($empleado, $data, $tipo, $moneda, $monto, $fecha) {
            if ($tipo === MovimientoPrestaciones::TIPO_ANTICIPO) {
                // El saldo de la garantía debe estar calculado al día del
                // movimiento para validar el máximo legal del anticipo. Si la
                // fecha es futura, se proyecta hasta su mes para que el saldo
                // operativo (y el máximo) incluya lo proyectado.
                $this->prestaciones->asegurarPeriodos($empleado, $fecha);
                $this->prestaciones->asegurarProyeccionHasta($empleado, $fecha);
                $this->validarAnticipo($empleado, $monto, $moneda, $fecha, $data['motivo'] ?? null);
            }

            $tasa = $this->tasas->tasaEn($fecha);

            $payload = [
                'patrono_id' => $empleado->patrono_id,
                'empleado_id' => $empleado->id,
                'tipo' => $tipo,
                'fecha' => $fecha,
                'fecha_comprobante' => $data['fecha_comprobante'] ?? now()->toDateString(),
                'concepto' => $data['concepto'] ?? null,
                'motivo' => $data['motivo'] ?? null,
                'monto' => $monto,
                'moneda' => $moneda,
                'tasa_cambio' => $moneda === 'USD' ? $tasa : null,
                'plazo_meses' => $data['plazo_meses'] ?? null,
                'interes_anual' => $data['interes_anual'] ?? null,
                'cuota' => $data['cuota'] ?? null,
                'saldo' => $monto,
                'observacion' => $data['observacion'] ?? null,
            ];

            $movimiento = MovimientoPrestaciones::create($payload);

            if ($tipo === MovimientoPrestaciones::TIPO_ANTICIPO) {
                $periodo = $this->prestaciones->periodoDeFecha($empleado, $fecha)
                    ?? $fecha->format('Y-m');
                $this->prestaciones->recalcularDesde($empleado, $periodo);
            }

            return $movimiento;
        });
    }

    /**
     * Valida las reglas de negocio del anticipo (Art. 144 LOTTT).
     */
    protected function validarAnticipo(Empleado $empleado, float $monto, string $moneda, CarbonInterface $fecha, ?string $motivo): void
    {
        $errores = [];

        if (! in_array($motivo, MovimientoPrestaciones::MOTIVOS_ANTICIPO, true)) {
            $errores[] = 'El motivo del anticipo debe ser vivienda, educación o salud.';
        }

        $max = $this->maximoAnticipo($empleado, $fecha);
        $maximoMoneda = $this->convertir($max['maximo'], $max['moneda'], $moneda, $fecha);

        if ($monto > $maximoMoneda) {
            $errores[] = sprintf(
                'El monto supera el máximo permitido (%.2f %s = %s%% del saldo %.2f %s).',
                $maximoMoneda, $moneda, $max['pct'], $max['maximo'], $max['moneda']
            );
        }

        if ($this->anticiposRestantesEnAnio($empleado, $fecha) <= 0) {
            $errores[] = sprintf(
                'Ya se agotaron los %d anticipos permitidos para el año %d.',
                $empleado->patrono->anticipos_max_anio, $fecha->year
            );
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages(['monto' => $errores]);
        }
    }

    /**
     * Convierte un monto entre Bs y USD usando la tasa BCV de una fecha.
     */
    public function convertir(float $monto, string $desde, string $hacia, ?CarbonInterface $fecha = null): float
    {
        if ($desde === $hacia) {
            return $monto;
        }

        $tasa = $this->tasas->tasaEn($fecha ?: now());

        if (! $tasa || $tasa <= 0) {
            return $monto;
        }

        return $desde === 'USD'
            ? round($monto * $tasa, 2)
            : round($monto / $tasa, 2);
    }

    /**
     * Registra el pago de un interés sobre un préstamo o anticipo.
     */
    public function registrarInteres(MovimientoPrestaciones $movimiento, CarbonInterface $fecha, float $monto, string $moneda, ?string $concepto = null): void
    {
        $tasa = $this->tasas->tasaEn($fecha);

        $movimiento->intereses()->create([
            'fecha' => $fecha->toDateString(),
            'monto' => $monto,
            'moneda' => $moneda,
            'tasa_cambio' => $moneda === 'USD' ? $tasa : null,
            'concepto' => $concepto,
        ]);
    }

    /**
     * Paga un préstamo: reduce el saldo hasta cero y lo marca pagado.
     */
    public function pagarPrestamo(MovimientoPrestaciones $movimiento, ?float $monto = null): void
    {
        $pago = $monto ?? (float) $movimiento->saldo;
        $nuevoSaldo = max(0, (float) $movimiento->saldo - $pago);

        $movimiento->update([
            'saldo' => $nuevoSaldo,
            'estatus' => $nuevoSaldo <= 0 ? 'pagado' : 'activo',
        ]);
    }

    /**
     * Anula un movimiento pendiente (no pagado) y recalcula la garantía desde
     * el mes del movimiento cuando se trata de un anticipo.
     */
    public function anular(MovimientoPrestaciones $movimiento): void
    {
        if ($movimiento->estatus === 'pagado') {
            throw ValidationException::withMessages([
                'estatus' => ['No se puede anular un movimiento ya pagado.'],
            ]);
        }

        if ($movimiento->estatus === 'anulado') {
            return;
        }

        DB::transaction(function () use ($movimiento) {
            $esAnticipo = $movimiento->tipo === MovimientoPrestaciones::TIPO_ANTICIPO;
            $empleado = $movimiento->empleado;
            $periodo = $empleado
                ? ($this->prestaciones->periodoDeFecha($empleado, $movimiento->fecha)
                    ?? $movimiento->fecha->format('Y-m'))
                : $movimiento->fecha->format('Y-m');

            $movimiento->update(['estatus' => 'anulado']);

            if ($esAnticipo && $empleado) {
                $this->prestaciones->recalcularDesde($empleado, $periodo);
            }
        });
    }

    /**
     * Elimina definitivamente un movimiento anulado, recalculando la garantía
     * desde el mes del movimiento cuando se trata de un anticipo.
     */
    public function eliminar(MovimientoPrestaciones $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            $esAnticipo = $movimiento->tipo === MovimientoPrestaciones::TIPO_ANTICIPO;
            $empleado = $movimiento->empleado;
            $periodo = $empleado
                ? ($this->prestaciones->periodoDeFecha($empleado, $movimiento->fecha)
                    ?? $movimiento->fecha->format('Y-m'))
                : $movimiento->fecha->format('Y-m');

            $movimiento->delete();

            if ($esAnticipo && $empleado) {
                $this->prestaciones->recalcularDesde($empleado, $periodo);
            }
        });
    }
}
