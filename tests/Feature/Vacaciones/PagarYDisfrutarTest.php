<?php

declare(strict_types=1);

namespace Tests\Feature\Vacaciones;

use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\User;
use App\Models\Vacacion;
use Tests\Feature\Seguridad\CreaEscenarioSeguridad;
use Tests\TestCase;

class PagarYDisfrutarTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_pagar_genera_el_recibo_y_deja_la_vacacion_en_pagada(): void
    {
        [$patrono, $vacacion] = $this->crearEscenario();

        $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('vacaciones.estatus', $vacacion), ['accion' => 'pagar'])
            ->assertRedirect(route('vacaciones.show', $vacacion));

        $vacacion->refresh();

        $this->assertSame('pagada', $vacacion->estatus_vacacion);
        $this->assertSame('aprobada', $vacacion->estatus_calculo);
        $this->assertDatabaseCount('vacaciones_recibos', 1);

        $recibo = $vacacion->recibos()->sole();
        $this->assertSame('2026-10-01', $recibo->fecha_inicio->toDateString());
        $this->assertSame('2026-10-22', $recibo->fecha_retiro->toDateString());
    }

    public function test_no_se_puede_marcar_disfrutada_si_no_esta_pagada(): void
    {
        [$patrono, $vacacion] = $this->crearEscenario();

        $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('vacaciones.estatus', $vacacion), ['accion' => 'disfrutar'])
            ->assertSessionHas('error');

        $this->assertSame('pendiente', $vacacion->fresh()->estatus_vacacion);
        $this->assertDatabaseCount('vacaciones_recibos', 0);
    }

    public function test_marcar_disfrutada_despues_del_pago(): void
    {
        [$patrono, $vacacion] = $this->crearEscenario();
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('vacaciones.estatus', $vacacion), ['accion' => 'pagar']);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('vacaciones.estatus', $vacacion), ['accion' => 'disfrutar'])
            ->assertRedirect(route('vacaciones.show', $vacacion));

        $this->assertSame('disfrutada', $vacacion->fresh()->estatus_vacacion);
        $this->assertDatabaseCount('vacaciones_recibos', 1);
    }

    public function test_el_pdf_no_genera_recibo_si_la_vacacion_no_esta_pagada(): void
    {
        [$patrono, $vacacion] = $this->crearEscenario();

        $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('vacaciones.pdf', $vacacion))
            ->assertRedirect(route('vacaciones.show', $vacacion))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('vacaciones_recibos', 0);
    }

    public function test_el_pdf_usa_el_recibo_existente_sin_duplicarlo(): void
    {
        [$patrono, $vacacion] = $this->crearEscenario();
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('vacaciones.estatus', $vacacion), ['accion' => 'pagar']);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('vacaciones.pdf', $vacacion))
            ->assertOk();

        $this->assertDatabaseCount('vacaciones_recibos', 1);
    }

    /**
     * @return array{0: Patrono, 1: Vacacion}
     */
    private function crearEscenario(): array
    {
        $patrono = $this->crearPatrono('J-90909090-1', 'Patrono vacaciones');
        $empleado = $this->crearEmpleado($patrono, 'V-90909091');

        return [$patrono, $this->crearVacacion($patrono, $empleado)];
    }

    private function crearVacacion(Patrono $patrono, Empleado $empleado): Vacacion
    {
        return Vacacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_calculo' => '2026-10-01',
            'fecha_inicio' => '2026-10-01',
            'fecha_inicio_real' => '2026-10-01',
            'fecha_fin_real' => '2026-10-21',
            'fecha_reintegro' => '2026-10-22',
            'nro_vacacion' => 3,
            'periodo_desde' => '2023-01-15',
            'periodo_hasta' => '2024-01-15',
            'dias_vacaciones' => 15,
            'dias_adicionales' => 2,
            'dias_bono' => 15,
            'dias_bono_adicionales' => 2,
            'salario_normal' => 300,
            'moneda' => 'USD',
            'estatus_calculo' => 'aprobada',
            'estatus_vacacion' => 'pendiente',
        ]);
    }
}
