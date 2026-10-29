<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\User;
use Tests\TestCase;

class AccesoPatronosTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_un_usuario_sin_patrono_asignado_no_accede_a_la_nomina(): void
    {
        $patrono = $this->crearPatrono('J-10000001-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Consulta);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('empleados.index'))
            ->assertForbidden();
    }

    public function test_un_usuario_solo_accede_a_los_patronos_asignados(): void
    {
        $patronoA = $this->crearPatrono('J-10000002-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-10000003-1', 'Patrono B');
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patronoA]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patronoA->id])
            ->get(route('empleados.index'))
            ->assertOk();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patronoB->id])
            ->get(route('empleados.index'))
            ->assertForbidden();
    }

    public function test_un_administrador_accede_a_cualquier_patrono(): void
    {
        $patronoA = $this->crearPatrono('J-10000004-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-10000005-1', 'Patrono B');
        $usuario = $this->crearUsuario(RolUsuario::Administrador, [$patronoA]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patronoB->id])
            ->get(route('empleados.index'))
            ->assertOk();
    }

    public function test_el_selector_solo_lista_los_patronos_asignados(): void
    {
        $patronoA = $this->crearPatrono('J-10000006-1', 'Patrono Visible');
        $patronoB = $this->crearPatrono('J-10000007-1', 'Patrono Oculto');
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patronoA]);

        $this->actingAs($usuario)
            ->get(route('entorno.seleccionar'))
            ->assertOk()
            ->assertSee('Patrono Visible')
            ->assertDontSee('Patrono Oculto');
    }

    public function test_el_selector_rechaza_un_patrono_no_asignado(): void
    {
        $patronoA = $this->crearPatrono('J-10000008-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-10000009-1', 'Patrono B');
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patronoA]);

        $this->actingAs($usuario)
            ->post(route('entorno.guardar'), ['patrono_id' => $patronoB->id])
            ->assertForbidden();

        $this->assertNull(session('patrono_id'));
    }

    public function test_la_cookie_de_contexto_es_cifrada_y_restaura_el_patrono(): void
    {
        $patrono = $this->crearPatrono('J-10000021-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patrono]);

        $respuesta = $this->actingAs($usuario)
            ->post(route('entorno.guardar'), ['patrono_id' => $patrono->id])
            ->assertRedirect(route('empleados.index'))
            ->assertCookie('scl_ctx');

        $cookieCifrada = $respuesta->getCookie('scl_ctx', false);

        $this->assertNotSame(json_encode(['patrono_id' => $patrono->id]), $cookieCifrada->getValue());

        $this->flushSession();

        $this->actingAs($usuario)
            ->withCookie('scl_ctx', $respuesta->getCookie('scl_ctx')->getValue())
            ->get(route('empleados.index'))
            ->assertOk();
    }

    public function test_el_contexto_rechaza_una_cookie_manipulada(): void
    {
        $patronoA = $this->crearPatrono('J-10000010-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-10000011-1', 'Patrono B');
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patronoA]);

        $this->actingAs($usuario)
            ->withCookie('scl_ctx', json_encode(['patrono_id' => $patronoB->id]))
            ->get(route('empleados.index'))
            ->assertRedirect(route('entorno.seleccionar'));

        $this->assertNull(session('patrono_id'));
    }

    public function test_un_usuario_no_puede_ver_un_empleado_de_otro_patrono(): void
    {
        $patronoA = $this->crearPatrono('J-10000012-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-10000013-1', 'Patrono B');
        $empleadoAjeno = $this->crearEmpleado($patronoB);
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patronoA]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patronoA->id])
            ->get(route('empleados.edit', $empleadoAjeno))
            ->assertNotFound();
    }

    public function test_un_analista_no_puede_mover_un_empleado_a_un_patrono_no_asignado(): void
    {
        $patronoA = $this->crearPatrono('J-10000014-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-10000015-1', 'Patrono B');
        $empleado = $this->crearEmpleado($patronoA);
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patronoA]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patronoA->id])
            ->post(route('empleados.cambiar-patrono', $empleado), [
                'nuevo_patrono_id' => $patronoB->id,
            ])
            ->assertForbidden();

        $this->assertSame($patronoA->id, $empleado->fresh()->patrono_id);
    }

    public function test_el_rol_consulta_puede_leer_pero_no_modificar_la_nomina(): void
    {
        $patrono = $this->crearPatrono('J-10000016-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Consulta, [$patrono]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('empleados.index'))
            ->assertOk();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.store'), $this->datosEmpleado())
            ->assertForbidden();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('sueldos.regenerar'))
            ->assertForbidden();
    }

    public function test_el_rol_analista_puede_gestionar_su_patrono(): void
    {
        $patrono = $this->crearPatrono('J-10000017-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patrono]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.store'), $this->datosEmpleado('V-76543210'))
            ->assertRedirect(route('empleados.index'));

        $this->assertDatabaseHas('empleados', [
            'patrono_id' => $patrono->id,
            'cedula' => 'V-76543210',
        ]);
    }

    public function test_solo_el_administrador_accede_a_los_catalogos_globales_y_usuarios(): void
    {
        $patrono = $this->crearPatrono('J-10000018-1', 'Patrono A');
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        foreach ([
            route('patronos.index'),
            route('feriados.index'),
            route('tasas.index'),
            route('tasas-interes.index'),
            route('salarios-minimos.index'),
            route('usuarios.index'),
        ] as $url) {
            $this->actingAs($analista)
                ->withSession(['patrono_id' => $patrono->id])
                ->get($url)
                ->assertForbidden();
        }

        $this->actingAs($administrador)->get(route('usuarios.index'))->assertOk();
        $this->actingAs($administrador)->get(route('patronos.index'))->assertOk();
    }

    public function test_un_administrador_sin_patronos_asignados_puede_entrar_al_contexto(): void
    {
        $patrono = $this->crearPatrono('J-10000019-1', 'Patrono A');
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        $this->actingAs($administrador)
            ->get(route('entorno.seleccionar'))
            ->assertOk()
            ->assertSee('Patrono A');
    }

    public function test_un_usuario_consulta_no_puede_editar_usuarios(): void
    {
        $patrono = $this->crearPatrono('J-10000020-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Consulta, [$patrono]);
        $otro = User::factory()->consulta()->create();

        $this->actingAs($usuario)
            ->get(route('usuarios.edit', $otro))
            ->assertForbidden();
    }
}
