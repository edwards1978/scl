<?php

declare(strict_types=1);

namespace Tests\Feature\Utilidades;

use App\Models\BonificacionFinAno;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\User;
use Tests\Feature\Seguridad\CreaEscenarioSeguridad;
use Tests\TestCase;

class ProtegerAprobadasTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_recalcular_una_bonificacion_aprobada_no_la_modifica(): void
    {
        $patrono = $this->crearPatrono('J-70707070-7', 'Patrono con aprobadas');
        $empleado = $this->crearEmpleado($patrono, 'V-70707071');
        $bonificacion = $this->crearBonificacion($patrono, $empleado, 2026, [
            'estatus' => 'aprobado',
            'fecha_aprobacion' => '2026-10-01',
            'fecha_emision' => '2026-10-01',
            'neto' => 1500,
        ]);

        $respuesta = $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.recalcular', $bonificacion->id));

        $respuesta->assertRedirect(route('utilidades.index', ['anio' => 2026]))
            ->assertSessionHas('error');

        $bonificacion->refresh();

        $this->assertSame('aprobado', $bonificacion->estatus);
        $this->assertSame('2026-10-01', $bonificacion->fecha_aprobacion?->toDateString());
        $this->assertSame('1500.00', (string) $bonificacion->neto);
    }

    public function test_el_calculo_masivo_omite_las_aprobadas_y_recalcula_las_pendientes(): void
    {
        $patrono = $this->crearPatrono('J-71717171-7', 'Patrono mixto');

        $aprobada = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-71717172'),
            2026,
            [
                'estatus' => 'aprobado',
                'fecha_aprobacion' => '2026-10-01',
                'fecha_emision' => '2026-10-01',
                'neto' => 1500,
            ]
        );

        $pendiente = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-71717173'),
            2026
        );

        $respuesta = $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.calcular'), [
                'anio' => '2026',
                'tipo_base' => 'ultimo',
            ]);

        $respuesta->assertRedirect(route('utilidades.index', ['anio' => 2026]))
            ->assertSessionHas('success', fn (string $mensaje): bool => str_contains($mensaje, '1 trabajador(es)')
                && str_contains($mensaje, 'Se omitieron 1'));

        $aprobada->refresh();
        $pendiente->refresh();

        $this->assertSame('aprobado', $aprobada->estatus);
        $this->assertSame('1500.00', (string) $aprobada->neto);
        $this->assertSame('calculado', $pendiente->estatus);
        $this->assertNotNull($pendiente->neto);
    }

    public function test_informa_cuando_todas_las_bonificaciones_estan_aprobadas(): void
    {
        $patrono = $this->crearPatrono('J-72727272-7', 'Patrono aprobado');
        $empleado = $this->crearEmpleado($patrono, 'V-72727273');

        $bonificacion = $this->crearBonificacion($patrono, $empleado, 2026, [
            'estatus' => 'aprobado',
            'fecha_aprobacion' => '2026-10-01',
            'fecha_emision' => '2026-10-01',
            'neto' => 1500,
        ]);

        $respuesta = $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.calcular'), [
                'anio' => '2026',
                'tipo_base' => 'ultimo',
            ]);

        $respuesta->assertRedirect(route('utilidades.index', ['anio' => 2026]))
            ->assertSessionHas('info', fn (string $mensaje): bool => str_contains($mensaje, 'No se recalculó ninguna bonificación'));

        $this->assertSame('aprobado', $bonificacion->fresh()->estatus);
    }

    public function test_recalcular_una_bonificacion_pendiente_sigue_funcionando(): void
    {
        $patrono = $this->crearPatrono('J-73737373-7', 'Patrono pendiente');
        $empleado = $this->crearEmpleado($patrono, 'V-73737374');
        $bonificacion = $this->crearBonificacion($patrono, $empleado, 2026);

        $respuesta = $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('utilidades.recalcular', $bonificacion->id));

        $respuesta->assertRedirect(route('utilidades.index', ['anio' => 2026]))
            ->assertSessionHas('success');

        $bonificacion->refresh();

        $this->assertSame('calculado', $bonificacion->estatus);
        $this->assertNotNull($bonificacion->neto);
    }

    public function test_el_listado_no_ofrece_recalcular_una_aprobada(): void
    {
        $patrono = $this->crearPatrono('J-74747474-7', 'Patrono listado');
        $bonificacion = $this->crearBonificacion(
            $patrono,
            $this->crearEmpleado($patrono, 'V-74747475'),
            2026,
            [
                'estatus' => 'aprobado',
                'fecha_aprobacion' => '2026-10-01',
                'fecha_emision' => '2026-10-01',
                'neto' => 1500,
            ]
        );

        $this->actingAs(User::factory()->administrador()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('utilidades.index', ['anio' => 2026]))
            ->assertOk()
            ->assertDontSee(route('utilidades.recalcular', $bonificacion->id));
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearBonificacion(Patrono $patrono, Empleado $empleado, int $anio, array $atributos = []): BonificacionFinAno
    {
        return BonificacionFinAno::create(array_merge([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'anio' => $anio,
            'periodo_inicio' => "{$anio}-01-01",
            'periodo_fin' => "{$anio}-12-31",
            'dias_base' => 30,
            'estatus' => 'calculado',
        ], $atributos));
    }
}
