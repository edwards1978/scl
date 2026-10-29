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

    public function test_registra_quien_elimina_y_quien_restaura(): void
    {
        $patrono = $this->crearPatrono('J-12000007-1', 'Patrono papelera');
        $empleado = $this->crearEmpleado($patrono);
        $elimina = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);
        $restaura = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $elimina->update(['name' => 'Elimina Uno']);
        $restaura->update(['name' => 'Restaura Dos']);

        $this->actingAs($elimina);
        $empleado->delete();

        $this->actingAs($restaura)
            ->get(route('papelera.index', ['tipo' => 'empleados']))
            ->assertOk()
            ->assertSee('Elimina Uno');

        $this->actingAs($restaura)
            ->post(route('papelera.restaurar', ['tipo' => 'empleados', 'id' => $empleado->id]))
            ->assertSessionHas('success');

        $eventos = $empleado->historialEstados()
            ->where('campo', 'papelera')
            ->reorder('id')
            ->get();

        $this->assertSame(['eliminado', 'restaurado'], $eventos->pluck('valor_nuevo')->all());
        $this->assertSame([$elimina->id, $restaura->id], $eventos->pluck('user_id')->all());
        $this->assertSame($patrono->id, $eventos->first()->patrono_id);
    }

    public function test_la_papelera_filtra_por_texto_y_por_patrono(): void
    {
        $patronoA = $this->crearPatrono('J-12000008-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-12000009-1', 'Patrono B');
        $empleadoA = $this->crearEmpleado($patronoA, 'V-12000081');
        $empleadoB = $this->crearEmpleado($patronoB, 'V-12000082');
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        $empleadoA->delete();
        $empleadoB->delete();

        $this->actingAs($administrador)
            ->get(route('papelera.index', ['tipo' => 'empleados', 'buscar' => 'V-12000081']))
            ->assertOk()
            ->assertSee('V-12000081')
            ->assertDontSee('V-12000082');

        $this->actingAs($administrador)
            ->get(route('papelera.index', ['tipo' => 'empleados', 'patrono_id' => $patronoA->id]))
            ->assertOk()
            ->assertSee('V-12000081')
            ->assertDontSee('V-12000082');
    }

    public function test_la_papelera_busca_por_el_trabajador_de_un_documento(): void
    {
        $patrono = $this->crearPatrono('J-12000012-1', 'Patrono búsqueda');
        $empleadoA = $this->crearEmpleado($patrono, 'V-12000121');
        $empleadoB = $this->crearEmpleado($patrono, 'V-12000122');
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $empleadoA->update(['nombres' => 'Zulema']);
        $empleadoB->update(['nombres' => 'Beatriz']);

        foreach ([$empleadoA, $empleadoB] as $indice => $empleado) {
            $vacacion = Vacacion::create([
                'patrono_id' => $patrono->id,
                'empleado_id' => $empleado->id,
                'fecha_calculo' => '2026-10-01',
                'fecha_inicio' => '2026-10-15',
                'nro_vacacion' => 2,
                'periodo_desde' => '2025-01-'.(15 + $indice),
                'periodo_hasta' => '2026-01-'.(15 + $indice),
                'moneda' => 'USD',
            ]);

            $vacacion->delete();
        }

        $this->actingAs($administrador)
            ->get(route('papelera.index', ['tipo' => 'vacaciones', 'buscar' => 'Zulema']))
            ->assertOk()
            ->assertSee('Zulema Pérez')
            ->assertDontSee('Beatriz Pérez');
    }

    public function test_no_restaura_un_registro_si_su_trabajador_sigue_en_la_papelera(): void
    {
        $patrono = $this->crearPatrono('J-12000010-1', 'Patrono papelera');
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

        $empleado->delete();
        $vacacion->delete();

        $this->actingAs($administrador)
            ->post(route('papelera.restaurar', ['tipo' => 'vacaciones', 'id' => $vacacion->id]))
            ->assertSessionHas('error');

        $this->assertSoftDeleted($vacacion);
        $this->assertSoftDeleted($empleado);
    }

    public function test_un_trabajador_con_historial_no_se_puede_eliminar(): void
    {
        $patrono = $this->crearPatrono('J-12000011-1', 'Patrono papelera');
        $empleado = $this->crearEmpleado($patrono);
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        Vacacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_calculo' => '2026-10-01',
            'fecha_inicio' => '2026-10-15',
            'nro_vacacion' => 2,
            'periodo_desde' => '2025-01-15',
            'periodo_hasta' => '2026-01-15',
            'moneda' => 'USD',
        ]);

        $this->assertTrue($empleado->tieneHistorialLaboral());

        $this->actingAs($administrador)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('empleados.index'))
            ->assertOk()
            ->assertDontSee('action="'.route('empleados.destroy', $empleado).'"', false);

        $this->actingAs($administrador)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('empleados.destroy', $empleado))
            ->assertRedirect(route('empleados.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($empleado);
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
