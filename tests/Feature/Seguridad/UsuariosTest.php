<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\User;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_un_administrador_crea_un_usuario_con_rol_y_patronos(): void
    {
        $patrono = $this->crearPatrono('J-20000001-1', 'Patrono A');
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        $this->actingAs($administrador)
            ->post(route('usuarios.store'), [
                'name' => 'Analista Uno',
                'email' => 'analista@example.com',
                'password' => 'Secreta123!',
                'password_confirmation' => 'Secreta123!',
                'rol' => RolUsuario::Analista->value,
                'patronos' => [$patrono->id],
            ])
            ->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'analista@example.com')->firstOrFail();

        $this->assertSame(RolUsuario::Analista, $usuario->rol);
        $this->assertTrue($usuario->tieneAcceso($patrono));
        $this->assertTrue(password_verify('Secreta123!', $usuario->password));

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('empleados.index'))
            ->assertOk();
    }

    public function test_un_analista_requiere_al_menos_un_patrono(): void
    {
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        $this->actingAs($administrador)
            ->post(route('usuarios.store'), [
                'name' => 'Sin Patrono',
                'email' => 'sinpatrono@example.com',
                'password' => 'Secreta123!',
                'password_confirmation' => 'Secreta123!',
                'rol' => RolUsuario::Analista->value,
                'patronos' => [],
            ])
            ->assertSessionHasErrors('patronos');

        $this->assertDatabaseMissing('users', ['email' => 'sinpatrono@example.com']);
    }

    public function test_un_administrador_actualiza_rol_y_patronos(): void
    {
        $patronoA = $this->crearPatrono('J-20000002-1', 'Patrono A');
        $patronoB = $this->crearPatrono('J-20000003-1', 'Patrono B');
        $administrador = $this->crearUsuario(RolUsuario::Administrador);
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patronoA]);

        $this->actingAs($administrador)
            ->put(route('usuarios.update', $usuario), [
                'name' => 'Usuario Actualizado',
                'email' => $usuario->email,
                'rol' => RolUsuario::Consulta->value,
                'patronos' => [$patronoB->id],
            ])
            ->assertRedirect(route('usuarios.index'));

        $usuario->refresh();

        $this->assertSame('Usuario Actualizado', $usuario->name);
        $this->assertSame(RolUsuario::Consulta, $usuario->rol);
        $this->assertFalse($usuario->tieneAcceso($patronoA));
        $this->assertTrue($usuario->tieneAcceso($patronoB));
    }

    public function test_no_se_puede_degradar_al_ultimo_administrador(): void
    {
        $patrono = $this->crearPatrono('J-20000004-1', 'Patrono A');
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        $this->actingAs($administrador)
            ->from(route('usuarios.edit', $administrador))
            ->put(route('usuarios.update', $administrador), [
                'name' => $administrador->name,
                'email' => $administrador->email,
                'rol' => RolUsuario::Consulta->value,
                'patronos' => [$patrono->id],
            ])
            ->assertRedirect(route('usuarios.edit', $administrador))
            ->assertSessionHasErrors('rol');

        $this->assertSame(RolUsuario::Administrador, $administrador->fresh()->rol);
    }

    public function test_un_administrador_no_puede_eliminar_su_propio_usuario(): void
    {
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        $this->actingAs($administrador)
            ->delete(route('usuarios.destroy', $administrador))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $administrador->id]);
    }

    public function test_un_usuario_no_administrador_no_accede_a_la_gestion_de_usuarios(): void
    {
        $patrono = $this->crearPatrono('J-20000005-1', 'Patrono A');
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('usuarios.index'))
            ->assertForbidden();

        $this->actingAs($analista)
            ->post(route('usuarios.store'), [])
            ->assertForbidden();
    }
}
