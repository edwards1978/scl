<?php

declare(strict_types=1);

namespace Tests\Feature\Prestaciones;

use App\Models\Empleado;
use App\Models\MovimientoPrestaciones;
use App\Models\Patrono;
use App\Models\User;
use Tests\TestCase;

class MovimientosPrestacionesTest extends TestCase
{
    public function test_el_listado_muestra_los_totales_otorgados_por_moneda(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado();

        $this->crearMovimiento($patrono, $empleado, MovimientoPrestaciones::TIPO_ANTICIPO, 100, 'USD');
        $this->crearMovimiento($patrono, $empleado, MovimientoPrestaciones::TIPO_PRESTAMO, 50, 'USD');
        $this->crearMovimiento($patrono, $empleado, MovimientoPrestaciones::TIPO_PRESTAMO, 200, 'BS');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('movimientos-prestaciones.index'))
            ->assertOk()
            ->assertSee('Total pagado en USD')
            ->assertSee('Total pagado en Bs.')
            ->assertViewHas('totales', fn ($totales) => (float) $totales['USD'] === 150.0
                && (float) $totales['BS'] === 200.0);
    }

    public function test_los_totales_respetan_los_filtros_del_listado(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado();

        $this->crearMovimiento($patrono, $empleado, MovimientoPrestaciones::TIPO_ANTICIPO, 100, 'USD');
        $this->crearMovimiento($patrono, $empleado, MovimientoPrestaciones::TIPO_PRESTAMO, 50, 'USD');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('movimientos-prestaciones.index', ['tipo' => MovimientoPrestaciones::TIPO_PRESTAMO]))
            ->assertOk()
            ->assertViewHas('totales', fn ($totales) => (float) $totales['USD'] === 50.0);
    }

    /**
     * @return array{0:Patrono, 1:Empleado}
     */
    private function crearPatronoEmpleado(): array
    {
        $patrono = Patrono::create([
            'rif' => 'J-32323232-3',
            'razon_social' => 'Patrono de pruebas',
            'estado' => 'Activo',
            'dias_bonificacion' => 30,
            'deposito_tipo' => 'contabilidad',
            'anticipos_max_anio' => 2,
            'anticipos_pct_contabilidad' => 100,
        ]);

        $empleado = Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'cedula' => 'V-32323232',
            'fecha_ingreso' => '2024-01-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);

        return [$patrono, $empleado];
    }

    private function crearMovimiento(
        Patrono $patrono,
        Empleado $empleado,
        string $tipo,
        float $monto,
        string $moneda,
    ): MovimientoPrestaciones {
        return MovimientoPrestaciones::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'tipo' => $tipo,
            'fecha' => '2026-10-10',
            'monto' => $monto,
            'moneda' => $moneda,
            'saldo' => $tipo === MovimientoPrestaciones::TIPO_PRESTAMO ? $monto : null,
            'estatus' => 'activo',
        ]);
    }
}
