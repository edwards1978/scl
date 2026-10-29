<?php

namespace Tests\Feature\Liquidaciones;

use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\MovimientoPrestaciones;
use App\Models\MovimientoPrestacionInteres;
use App\Models\Patrono;
use App\Models\TasaInteresPrestaciones;
use App\Services\LiquidacionService;
use App\Services\PrestacionesSocialesService;
use App\Services\SalarioIntegralService;
use Carbon\Carbon;
use Tests\TestCase;

class LiquidacionLegalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_la_garantia_se_desglosa_en_capital_e_intereses_y_la_indemnizacion_no_los_incluye(): void
    {
        [$patrono, $empleado] = $this->crearEscenario();

        $datos = $this->calcular($empleado);

        $prestaciones = $this->concepto($datos, 'Prestaciones sociales');
        $intereses = $this->concepto($datos, 'Intereses sobre prestaciones');
        $indemnizacion = $this->concepto($datos, 'Indemnización por terminación');

        $this->assertSame('garantia', $datos['prestaciones_origen']);
        $this->assertGreaterThan(0, $intereses);
        $this->assertEqualsWithDelta($datos['garantia_ab'], $prestaciones + $intereses, 0.01);
        $this->assertEqualsWithDelta($prestaciones, $indemnizacion, 0.01);
        $this->assertStringContainsString('sin intereses', $this->baseLegal($datos, 'Indemnización por terminación'));
    }

    public function test_si_gana_el_literal_c_los_intereses_se_pagan_aparte(): void
    {
        [$patrono, $empleado] = $this->crearEscenario();

        CambioSalario::create([
            'empleado_id' => $empleado->id,
            'fecha_cambio' => '2026-01-01',
            'monto' => 2000,
            'moneda' => 'USD',
        ]);

        $datos = $this->calcular($empleado);

        $prestaciones = $this->concepto($datos, 'Prestaciones sociales');
        $intereses = $this->concepto($datos, 'Intereses sobre prestaciones');
        $indemnizacion = $this->concepto($datos, 'Indemnización por terminación');

        $this->assertSame('literal_c', $datos['prestaciones_origen']);
        $this->assertGreaterThan(0, $intereses);
        $this->assertEqualsWithDelta($datos['literal_c'], $prestaciones, 0.01);
        $this->assertEqualsWithDelta($datos['literal_c'], $indemnizacion, 0.01);
    }

    public function test_los_prestamos_se_descuentan_por_capital_con_tope_del_50_por_ciento(): void
    {
        [$patrono, $empleado] = $this->crearEscenario();

        $prestamo = MovimientoPrestaciones::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'tipo' => MovimientoPrestaciones::TIPO_PRESTAMO,
            'fecha' => '2026-09-01',
            'concepto' => 'Préstamo de prueba',
            'monto' => 10000,
            'moneda' => 'USD',
            'saldo' => 10000,
            'estatus' => 'activo',
        ]);

        MovimientoPrestacionInteres::create([
            'movimiento_prestacion_id' => $prestamo->id,
            'fecha' => '2026-09-15',
            'monto' => 500,
            'moneda' => 'USD',
            'concepto' => 'Interés de préstamo',
        ]);

        $datos = $this->calcular($empleado);

        $prestamoRetenido = $this->deduccion($datos, 'Préstamos (capital)');
        $limite = round($datos['total_asignaciones'] * 0.50, 2);

        $this->assertEqualsWithDelta($limite, $prestamoRetenido, 0.01);
        $this->assertFalse(collect($datos['deducciones'])->contains('concepto', 'Pago de intereses'));
    }

    public function test_antes_de_tres_meses_paga_literal_e(): void
    {
        [$patrono, $empleado] = $this->crearEscenario('2026-09-05', 'V-20202020');

        $datos = $this->calcular($empleado);

        $this->assertSame('literal_e', $datos['prestaciones_origen']);
        $this->assertSame(10, $datos['dias_prestaciones']);
        $this->assertGreaterThan(0, $datos['garantia_ab']);
        $this->assertStringContainsString('literal e', $this->baseLegal($datos, 'Prestaciones sociales'));
    }

    public function test_el_trimestre_en_curso_se_paga_completo_al_egreso(): void
    {
        [$patrono, $empleado] = $this->crearEscenario('2026-06-01', 'V-30303030');

        $egreso = Carbon::parse('2026-10-20');
        $datos = $this->calcular($empleado);
        $integral = app(SalarioIntegralService::class)->diario($empleado, $egreso);
        $detalle = app(PrestacionesSocialesService::class)->garantiaDetalladaAlEgreso($empleado, $egreso);

        $this->assertSame('garantia', $datos['prestaciones_origen']);
        $this->assertEqualsWithDelta(
            round(30 * (float) $integral['salario_diario_integral'], 2),
            $detalle['capital'],
            0.01
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function calcular(Empleado $empleado): array
    {
        TasaInteresPrestaciones::create([
            'periodo' => '2026-01',
            'tasa_interes' => 12,
            'fuente' => 'manual',
        ]);

        return app(LiquidacionService::class)->calcular(
            $empleado,
            Carbon::parse('2026-10-20'),
            'despido_no_justificado'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $conceptos
     */
    private function concepto(array $datos, string $nombre): float
    {
        $concepto = collect($datos['asignaciones'])->firstWhere('concepto', $nombre);

        $this->assertNotNull($concepto, "No se encontró el concepto {$nombre}");

        return (float) $concepto['importe'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $datos
     */
    private function deduccion(array $datos, string $nombre): float
    {
        $concepto = collect($datos['deducciones'])->firstWhere('concepto', $nombre);

        $this->assertNotNull($concepto, "No se encontró la deducción {$nombre}");

        return (float) $concepto['importe'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $datos
     */
    private function baseLegal(array $datos, string $nombre): string
    {
        $concepto = collect($datos['asignaciones'])->firstWhere('concepto', $nombre);

        $this->assertNotNull($concepto, "No se encontró el concepto {$nombre}");

        return (string) $concepto['base_legal'];
    }

    /**
     * @return array{0: Patrono, 1: Empleado}
     */
    private function crearEscenario(string $fechaIngreso = '2020-01-15', string $cedula = 'V-10101010'): array
    {
        $patrono = Patrono::create([
            'rif' => 'J-10101010-1',
            'razon_social' => 'Patrono legal',
            'estado' => 'Activo',
            'dias_bonificacion' => 30,
            'deposito_tipo' => 'contabilidad',
            'anticipos_max_anio' => 2,
            'anticipos_pct_contabilidad' => 100,
        ]);

        $empleado = Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Ana',
            'apellidos' => 'Legal',
            'cedula' => $cedula,
            'fecha_ingreso' => $fechaIngreso,
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'salario_base' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);

        return [$patrono, $empleado];
    }
}
