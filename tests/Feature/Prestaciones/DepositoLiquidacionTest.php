<?php

namespace Tests\Feature\Prestaciones;

use App\Models\Empleado;
use App\Models\Patrono;
use App\Services\PrestacionesSocialesService;
use Carbon\Carbon;
use Tests\TestCase;

class DepositoLiquidacionTest extends TestCase
{
    public function test_la_opcion_predeterminada_es_el_trimestre_completo(): void
    {
        $patrono = $this->crearPatrono();

        $this->assertSame(Patrono::DEPOSITO_LIQUIDACION_COMPLETO, $patrono->deposito_liquidacion_trimestre);
    }

    public function test_las_tres_opciones_calculan_los_dias_correctos(): void
    {
        $patrono = $this->crearPatrono();
        $empleado = $this->crearEmpleado($patrono);
        $servicio = app(PrestacionesSocialesService::class);

        $inicio = Carbon::parse('2026-10-01');
        $cierre = Carbon::parse('2026-11-20');

        $this->assertSame(15, $servicio->diasDepositoLiquidacion($empleado, $inicio, $cierre));

        $patrono->update(['deposito_liquidacion_trimestre' => Patrono::DEPOSITO_LIQUIDACION_PRORRATEADO]);
        $empleado->load('patrono');
        $this->assertSame(10, $servicio->diasDepositoLiquidacion($empleado, $inicio, $cierre));
        $this->assertSame(10, $servicio->calcularMes(
            $empleado,
            Carbon::parse('2026-11'),
            false,
            $cierre
        )['dias_deposito']);

        $patrono->update(['deposito_liquidacion_trimestre' => Patrono::DEPOSITO_LIQUIDACION_NINGUNO]);
        $empleado->load('patrono');
        $this->assertSame(0, $servicio->diasDepositoLiquidacion($empleado, $inicio, $cierre));
    }

    public function test_la_opcion_no_afecta_los_cierres_trimestrales_normales(): void
    {
        $patrono = $this->crearPatrono(Patrono::DEPOSITO_LIQUIDACION_NINGUNO);
        $empleado = $this->crearEmpleado($patrono, '2026-01-15');

        $calculo = app(PrestacionesSocialesService::class)->calcularMes(
            $empleado,
            Carbon::parse('2026-03')
        );

        $this->assertSame(15, $calculo['dias_deposito']);
    }

    private function crearPatrono(string $opcion = Patrono::DEPOSITO_LIQUIDACION_COMPLETO): Patrono
    {
        return Patrono::create([
            'rif' => 'J-'.random_int(10000000, 99999999).'-1',
            'razon_social' => 'Patrono de depósito',
            'estado' => 'Activo',
            'dias_bonificacion' => 30,
            'deposito_tipo' => 'contabilidad',
            'deposito_liquidacion_trimestre' => $opcion,
            'anticipos_max_anio' => 2,
            'anticipos_pct_contabilidad' => 100,
        ]);
    }

    private function crearEmpleado(Patrono $patrono, string $fechaIngreso = '2026-07-01'): Empleado
    {
        return Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Ana',
            'apellidos' => 'Depósito',
            'cedula' => 'V-'.random_int(10000000, 99999999),
            'fecha_ingreso' => $fechaIngreso,
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'salario_base' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);
    }
}
