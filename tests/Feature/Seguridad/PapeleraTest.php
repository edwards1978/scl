<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\Vacacion;
use App\Models\VacacionesRecibo;
use Tests\TestCase;

class PapeleraTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_solo_el_administrador_accede_a_la_papelera(): void
    {
        $patrono = $this->crearPatrono('J-12000001-1', 'Patrono papelera');
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('papelera.index'))
            ->assertForbidden();

        $this->actingAs($administrador)
            ->get(route('papelera.index'))
            ->assertOk()
            ->assertSee('Papelera');
    }

    public function test_el_administrador_restaura_un_empleado_eliminado(): void
    {
        $patrono = $this->crearPatrono('J-12000002-1', 'Patrono papelera');
        $empleado = $this->crearEmpleado($patrono);
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $empleado->delete();

        $this->assertSoftDeleted($empleado);

        $this->actingAs($administrador)
            ->get(route('papelera.index', ['tipo' => 'empleados']))
            ->assertOk()
            ->assertSee($empleado->cedula);

        $this->actingAs($administrador)
            ->post(route('papelera.restaurar', ['tipo' => 'empleados', 'id' => $empleado->id]))
            ->assertRedirect(route('papelera.index', ['tipo' => 'empleados']))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($empleado);
    }

    public function test_un_empleado_sin_historial_puede_eliminarse_aunque_tenga_su_sueldo_de_ingreso(): void
    {
        $patrono = $this->crearPatrono('J-12000006-1', 'Patrono papelera');
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $this->actingAs($administrador)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.store'), $this->datosEmpleado('V-12000061'))
            ->assertRedirect(route('empleados.index'));

        $empleado = Empleado::where('cedula', 'V-12000061')->firstOrFail();

        $this->assertDatabaseHas('cambios_salarios', [
            'empleado_id' => $empleado->id,
            'fecha_cambio' => $empleado->fecha_ingreso->toDateString(),
        ]);

        $this->actingAs($administrador)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('empleados.destroy', $empleado))
            ->assertRedirect(route('empleados.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($empleado);
    }

    public function test_restaurar_una_vacacion_recupera_sus_recibos_eliminados(): void
    {
        $patrono = $this->crearPatrono('J-12000003-1', 'Patrono papelera');
        $empleado = $this->crearEmpleado($patrono);
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $vacacion = Vacacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_calculo' => '2026-10-01',
            'fecha_inicio' => '2026-10-15',
            'nro_vacacion' => 2,
            'periodo_desde' => '2025-01-15',
            'periodo_hasta' => '2026-01-15',
            'moneda' => 'USD',
        ]);

        $recibo = VacacionesRecibo::create([
            'vacacion_id' => $vacacion->id,
            'empleado_id' => $empleado->id,
            'fecha_emision' => '2026-10-01',
            'fecha_inicio' => '2026-10-15',
            'fecha_retiro' => '2026-10-30',
            'asignaciones' => [],
            'deducciones' => [],
            'moneda' => 'USD',
        ]);

        $vacacion->delete();
        $recibo->delete();

        $this->actingAs($administrador)
            ->post(route('papelera.restaurar', ['tipo' => 'vacaciones', 'id' => $vacacion->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($vacacion);
        $this->assertNotSoftDeleted($recibo);
    }

    public function test_restaurar_un_cambio_salarial_actualiza_la_ficha_del_trabajador(): void
    {
        $patrono = $this->crearPatrono('J-12000004-1', 'Patrono papelera');
        $empleado = $this->crearEmpleado($patrono);
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $cambio = CambioSalario::create([
            'empleado_id' => $empleado->id,
            'fecha_cambio' => now()->subMonth()->toDateString(),
            'monto' => 850,
            'moneda' => 'USD',
        ]);

        $cambio->delete();
        $empleado->update(['salario_base' => 300]);

        $this->actingAs($administrador)
            ->post(route('papelera.restaurar', ['tipo' => 'cambios-salarios', 'id' => $cambio->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($cambio);
        $this->assertSame('850.00', $empleado->fresh()->salario_base);
    }

    public function test_no_permite_crear_un_patrono_con_el_rif_de_uno_eliminado(): void
    {
        $patrono = $this->crearPatrono('J-12000005-1', 'Patrono papelera');
        $administrador = $this->crearUsuario(RolUsuario::Administrador);
        $patrono->delete();

        $this->actingAs($administrador)
            ->post(route('patronos.store'), [
                'rif' => $patrono->rif,
                'razon_social' => 'Patrono duplicado',
                'deposito_tipo' => 'contabilidad',
            ])
            ->assertSessionHasErrors('rif');

        $this->assertDatabaseCount('patronos', 1);
    }
}
