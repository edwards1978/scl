<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Livewire\Entidades\CrearEmpleado;
use App\Livewire\Entidades\CrearPatrono;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireAutorizacionTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_el_rol_consulta_no_puede_montar_el_formulario_de_empleados(): void
    {
        $patrono = $this->crearPatrono('J-30000001-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Consulta, [$patrono]);
        session(['patrono_id' => $patrono->id]);

        Livewire::actingAs($usuario)
            ->test(CrearEmpleado::class)
            ->assertForbidden();
    }

    public function test_el_rol_analista_puede_montar_el_formulario_de_empleados(): void
    {
        $patrono = $this->crearPatrono('J-30000002-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Analista, [$patrono]);
        session(['patrono_id' => $patrono->id]);

        Livewire::actingAs($usuario)
            ->test(CrearEmpleado::class)
            ->assertStatus(200);
    }

    public function test_solo_el_administrador_puede_montar_el_formulario_de_patronos(): void
    {
        $patrono = $this->crearPatrono('J-30000003-1', 'Patrono A');
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);

        Livewire::actingAs($analista)
            ->test(CrearPatrono::class)
            ->assertForbidden();
    }
}
