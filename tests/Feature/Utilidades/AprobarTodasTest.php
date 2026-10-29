<?php

declare(strict_types=1);

namespace Tests\Feature\Utilidades;

use App\Models\BonificacionFinAno;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\User;
use Tests\TestCase;

class AprobarTodasTest extends TestCase
{
    public function test_aprueba_todas_las_pendientes_del_anio_filtrado(): void
    {
        $patrono = $this->crearPatrono('J-50505050-5', 'Patrono utilidades');

        $pendiente2026 = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-50505051', 'Uno'),
            2026
        );

        $otraPendiente2026 = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-50505052', 'Dos'),
            2026
        );

        $pendiente2025 = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-50505053', 'Tres'),
            2025
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.aprobar-todas'), ['anio' => '2026']);

        $respuesta->assertRedirect(route('utilidades.index', ['anio' => 2026]))
            ->assertSessionHas('success');

        foreach ([$pendiente2026, $otraPendiente2026] as $bonificacion) {
            $bonificacion->refresh();

            $this->assertSame('aprobado', $bonificacion->estatus);
            $this->assertNotNull($bonificacion->fecha_aprobacion);
            $this->assertNotNull($bonificacion->fecha_emision);
        }

        $this->assertSame('calculado', $pendiente2025->fresh()->estatus);
    }

    public function test_aprueba_todas_las_pendientes_de_todos_los_anios(): void
    {
        $patrono = $this->crearPatrono('J-60606060-6', 'Patrono utilidades');

        $bonificacion2026 = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-60606061', 'Uno'),
            2026
        );

        $bonificacion2025 = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-60606062', 'Dos'),
            2025
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.aprobar-todas'), ['anio' => 'todos']);

        $respuesta->assertRedirect(route('utilidades.index', ['anio' => 'todos']))
            ->assertSessionHas('success');

        $this->assertSame('aprobado', $bonificacion2026->fresh()->estatus);
        $this->assertSame('aprobado', $bonificacion2025->fresh()->estatus);
    }

    public function test_no_aprueba_bonificaciones_de_otro_patrono(): void
    {
        $patrono = $this->crearPatrono('J-70707070-7', 'Patrono propio');
        $otroPatrono = $this->crearPatrono('J-80808080-8', 'Patrono ajeno');

        $this->crearEmpleado($patrono, 'V-70707071', 'Propio');

        $ajena = $this->crearBonificacion(
            $otroPatrono,
            $this->crearEmpleado($otroPatrono, 'V-80808081', 'Ajeno'),
            2026
        );

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.aprobar-todas'), ['anio' => '2026'])
            ->assertRedirect(route('utilidades.index', ['anio' => 2026]))
            ->assertSessionHas('info');

        $this->assertSame('calculado', $ajena->fresh()->estatus);
    }

    public function test_informa_cuando_no_hay_pendientes(): void
    {
        $patrono = $this->crearPatrono('J-90909090-9', 'Patrono sin pendientes');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.aprobar-todas'), ['anio' => '2026'])
            ->assertRedirect(route('utilidades.index', ['anio' => 2026]))
            ->assertSessionHas('info', 'No hay bonificaciones pendientes por aprobar.');
    }

    public function test_el_listado_muestra_el_boton_con_el_total_de_pendientes(): void
    {
        $patrono = $this->crearPatrono('J-10101010-1', 'Patrono con pendientes');

        $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-10101011', 'Uno'),
            2026
        );

        $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-10101012', 'Dos'),
            2026
        );

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('utilidades.index', ['anio' => 2026]))
            ->assertOk()
            ->assertSee('Aprobar todas las pendientes (2)');
    }

    private function crearPatrono(string $rif, string $razonSocial): Patrono
    {
        return Patrono::create([
            'rif' => $rif,
            'razon_social' => $razonSocial,
            'estado' => 'Activo',
        ]);
    }

    private function crearEmpleado(Patrono $patrono, string $cedula, string $nombres): Empleado
    {
        return Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => $nombres,
            'apellidos' => 'Prueba',
            'cedula' => $cedula,
            'fecha_ingreso' => '2024-01-01',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_base' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);
    }

    private function crearBonificacion(Patrono $patrono, Empleado $empleado, int $anio): BonificacionFinAno
    {
        return BonificacionFinAno::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'anio' => $anio,
            'periodo_inicio' => "{$anio}-01-01",
            'periodo_fin' => "{$anio}-12-31",
            'estatus' => 'calculado',
        ]);
    }
}
