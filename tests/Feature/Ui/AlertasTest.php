<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

class AlertasTest extends TestCase
{
    public function test_las_alertas_tienen_cierre_manual_y_automatico(): void
    {
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario)
            ->withSession(['success' => 'Operación guardada correctamente.'])
            ->get(route('calculos-literal-c.index'))
            ->assertOk()
            ->assertSee('Operación guardada correctamente.')
            ->assertSee('data-cerrar-alerta', false)
            ->assertSee('data-duracion="8000"', false)
            ->assertSee('Cerrar mensaje');
    }

    public function test_los_errores_de_validacion_se_muestran_en_alerta_cerrable(): void
    {
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario)
            ->from(route('calculos-literal-c.create'))
            ->post(route('calculos-literal-c.store'), [])
            ->assertRedirect(route('calculos-literal-c.create'))
            ->assertSessionHasErrors();

        $this->actingAs($usuario)
            ->get(route('calculos-literal-c.create'))
            ->assertOk()
            ->assertSee('Revisa los datos:')
            ->assertSee('data-cerrar-alerta', false);
    }
}
