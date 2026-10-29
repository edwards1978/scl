<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\User;
use Tests\TestCase;

class FiltrosDeListadosTest extends TestCase
{
    private const CLASE_CUADRO = 'mb-4 bg-white border border-gray-200 p-3 flex flex-wrap items-end gap-3';

    public function test_el_listado_de_patronos_presenta_los_filtros_en_un_cuadro(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('patronos.index'))
            ->assertOk()
            ->assertSee(self::CLASE_CUADRO, false)
            ->assertSee('Filtrar');
    }

    public function test_el_listado_de_empleados_presenta_los_filtros_en_un_cuadro(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('empleados.index'))
            ->assertOk()
            ->assertSee(self::CLASE_CUADRO, false)
            ->assertSee('Filtrar');
    }

    public function test_el_listado_de_vacaciones_presenta_los_filtros_en_un_cuadro(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('vacaciones.index'))
            ->assertOk()
            ->assertSee(self::CLASE_CUADRO, false)
            ->assertSee('Filtrar');
    }

    public function test_el_listado_de_prestaciones_presenta_los_filtros_en_un_cuadro_y_el_estatus(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.index'))
            ->assertOk()
            ->assertSee(self::CLASE_CUADRO, false)
            ->assertSee('Filtrar')
            ->assertSee('Estatus');
    }

    /**
     * @return array{0:Patrono, 1:Empleado}
     */
    private function crearPatronoEmpleado(): array
    {
        $patrono = Patrono::create([
            'rif' => 'J-30303030-1',
            'razon_social' => 'Patrono de prueba',
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
            'cedula' => 'V-30303030',
            'fecha_ingreso' => '2024-01-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);

        return [$patrono, $empleado];
    }
}
