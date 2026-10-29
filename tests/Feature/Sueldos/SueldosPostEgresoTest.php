<?php

declare(strict_types=1);

namespace Tests\Feature\Sueldos;

use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\Patrono;
use App\Models\Sueldo;
use App\Models\User;
use App\Services\SueldosService;
use Carbon\Carbon;
use Tests\Feature\Seguridad\CreaEscenarioSeguridad;
use Tests\TestCase;

class SueldosPostEgresoTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_no_genera_periodos_posteriores_al_egreso_de_una_liquidacion_aprobada(): void
    {
        $patrono = $this->crearPatrono('J-80808080-8', 'Patrono con egreso');
        $empleado = $this->crearEmpleado($patrono, 'V-80808081');

        $this->crearLiquidacion($patrono, $empleado, '2025-06-10', 'aprobada');

        $periodos = (new SueldosService)->periodosRequeridos($empleado, Carbon::parse('2026-10-31'));

        $this->assertSame('2025-06', end($periodos));
        $this->assertContains('2025-05', $periodos);
        $this->assertNotContains('2025-07', $periodos);
    }

    public function test_una_liquidacion_anulada_no_limita_los_periodos(): void
    {
        $patrono = $this->crearPatrono('J-81818181-8', 'Patrono con anulada');
        $empleado = $this->crearEmpleado($patrono, 'V-81818182');

        $this->crearLiquidacion($patrono, $empleado, '2025-06-10', 'anulada');

        $periodos = (new SueldosService)->periodosRequeridos($empleado, Carbon::parse('2026-10-31'));

        $this->assertSame('2026-10', end($periodos));
    }

    public function test_asegurar_sueldos_no_crea_filas_despues_del_egreso(): void
    {
        $patrono = $this->crearPatrono('J-82828282-8', 'Patrono asegurado');
        $empleado = $this->crearEmpleado($patrono, 'V-82828283');

        $this->crearLiquidacion($patrono, $empleado, '2025-06-10', 'aprobada');

        (new SueldosService)->asegurarSueldos($empleado);

        $this->assertSame('2025-06', Sueldo::where('empleado_id', $empleado->id)->max('periodo'));
        $this->assertDatabaseHas('sueldos', [
            'empleado_id' => $empleado->id,
            'periodo' => '2025-06',
        ]);
        $this->assertDatabaseMissing('sueldos', [
            'empleado_id' => $empleado->id,
            'periodo' => '2025-07',
        ]);
    }

    public function test_regenerar_elimina_las_filas_posteriores_al_egreso_y_no_las_recrea(): void
    {
        $patrono = $this->crearPatrono('J-83838383-8', 'Patrono regenerado');
        $empleado = $this->crearEmpleado($patrono, 'V-83838384');

        $this->crearLiquidacion($patrono, $empleado, '2025-06-10', 'aprobada');

        Sueldo::create([
            'empleado_id' => $empleado->id,
            'periodo' => '2025-07',
            'fecha_cierre' => '2025-07-31',
            'moneda_sueldo' => 'USD',
            'monto_usd' => 300,
            'monto_bs' => 300,
        ]);

        (new SueldosService)->regenerarSueldos($empleado);

        $this->assertDatabaseMissing('sueldos', [
            'empleado_id' => $empleado->id,
            'periodo' => '2025-07',
        ]);
    }

    public function test_aprobar_la_liquidacion_elimina_los_sueldos_posteriores_al_egreso(): void
    {
        $patrono = $this->crearPatrono('J-84848484-8', 'Patrono aprobado');
        $empleado = $this->crearEmpleado($patrono, 'V-84848485');

        (new SueldosService)->asegurarSueldos($empleado);

        $this->assertDatabaseHas('sueldos', [
            'empleado_id' => $empleado->id,
            'periodo' => '2025-07',
        ]);

        $liquidacion = $this->crearLiquidacion($patrono, $empleado, '2025-06-30', 'por_aprobar');

        $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('liquidaciones.aprobar', $liquidacion->id))
            ->assertRedirect(route('liquidaciones.show', $liquidacion->id));

        $this->assertSame('2025-06', Sueldo::where('empleado_id', $empleado->id)->max('periodo'));
        $this->assertDatabaseMissing('sueldos', [
            'empleado_id' => $empleado->id,
            'periodo' => '2025-07',
        ]);
        $this->assertSame('Inactivo', $empleado->fresh()->estatus);
    }

    public function test_anular_la_liquidacion_restablece_los_sueldos_posteriores(): void
    {
        $patrono = $this->crearPatrono('J-85858585-8', 'Patrono anulado');
        $empleado = $this->crearEmpleado($patrono, 'V-85858586');

        (new SueldosService)->asegurarSueldos($empleado);

        $liquidacion = $this->crearLiquidacion($patrono, $empleado, '2025-06-30', 'por_aprobar');
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('liquidaciones.aprobar', $liquidacion->id));

        $this->assertSame('2025-06', Sueldo::where('empleado_id', $empleado->id)->max('periodo'));

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('liquidaciones.anular', $liquidacion->id))
            ->assertRedirect(route('liquidaciones.show', $liquidacion->id));

        $this->assertSame('Activo', $empleado->fresh()->estatus);
        $this->assertSame(now()->format('Y-m'), Sueldo::where('empleado_id', $empleado->id)->max('periodo'));
    }

    private function crearLiquidacion(Patrono $patrono, Empleado $empleado, string $fechaEgreso, string $estatus): Liquidacion
    {
        return Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => $fechaEgreso,
            'fecha_ingreso' => $empleado->fecha_ingreso->toDateString(),
            'fecha_egreso' => $fechaEgreso,
            'motivo' => 'retiro_voluntario',
            'anios_servicio' => 1,
            'meses_fraccion' => 0,
            'dias_prestaciones' => 60,
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
            'neto' => 600,
            'estatus' => $estatus,
        ]);
    }
}
