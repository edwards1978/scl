<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\Patrono;
use App\Models\Vacacion;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermisosPorAccionTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_los_permisos_por_accion_corresponden_al_rol(): void
    {
        $administrador = $this->crearUsuario(RolUsuario::Administrador);
        $analista = $this->crearUsuario(RolUsuario::Analista);
        $consulta = $this->crearUsuario(RolUsuario::Consulta);

        $this->assertTrue(Gate::forUser($administrador)->allows('administrar'));
        $this->assertTrue(Gate::forUser($administrador)->allows('gestionar'));
        $this->assertTrue(Gate::forUser($administrador)->allows('aprobar'));
        $this->assertTrue(Gate::forUser($administrador)->allows('eliminar'));

        $this->assertFalse(Gate::forUser($analista)->allows('administrar'));
        $this->assertTrue(Gate::forUser($analista)->allows('gestionar'));
        $this->assertTrue(Gate::forUser($analista)->allows('aprobar'));
        $this->assertFalse(Gate::forUser($analista)->allows('eliminar'));

        $this->assertFalse(Gate::forUser($consulta)->allows('administrar'));
        $this->assertFalse(Gate::forUser($consulta)->allows('gestionar'));
        $this->assertFalse(Gate::forUser($consulta)->allows('aprobar'));
        $this->assertFalse(Gate::forUser($consulta)->allows('eliminar'));
    }

    public function test_el_analista_aprueba_pero_solo_el_administrador_elimina_una_liquidacion(): void
    {
        $patrono = $this->crearPatrono('J-11000001-1', 'Patrono permisos');
        $empleado = $this->crearEmpleado($patrono);
        $liquidacion = $this->crearLiquidacion($patrono, $empleado);
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('liquidaciones.aprobar', $liquidacion->id))
            ->assertRedirect();

        $this->assertSame('aprobada', $liquidacion->fresh()->estatus);

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('liquidaciones.destroy', $liquidacion->id))
            ->assertForbidden();

        $this->assertNotSoftDeleted($liquidacion);

        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);
        $otra = $this->crearLiquidacion($patrono, $this->crearEmpleado($patrono, 'V-11000002'));

        $this->actingAs($administrador)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('liquidaciones.destroy', $otra->id))
            ->assertRedirect(route('liquidaciones.index'));

        $this->assertSoftDeleted($otra);
    }

    public function test_el_analista_aprueba_vacaciones_y_no_puede_eliminarlas(): void
    {
        $patrono = $this->crearPatrono('J-11000003-1', 'Patrono permisos');
        $empleado = $this->crearEmpleado($patrono);
        $vacacion = $this->crearVacacion($patrono, $empleado);
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('vacaciones.estatus', $vacacion), ['accion' => 'aprobar'])
            ->assertRedirect();

        $this->assertSame('aprobada', $vacacion->fresh()->estatus_calculo);

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('vacaciones.destroy', $vacacion))
            ->assertForbidden();

        $this->assertNotSoftDeleted($vacacion);
    }

    public function test_la_consulta_no_puede_aprobar_ni_anular(): void
    {
        $patrono = $this->crearPatrono('J-11000004-1', 'Patrono permisos');
        $empleado = $this->crearEmpleado($patrono);
        $liquidacion = $this->crearLiquidacion($patrono, $empleado);
        $consulta = $this->crearUsuario(RolUsuario::Consulta, [$patrono]);

        $this->actingAs($consulta)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('liquidaciones.aprobar', $liquidacion->id))
            ->assertForbidden();

        $this->actingAs($consulta)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('liquidaciones.anular', $liquidacion->id))
            ->assertForbidden();

        $this->assertSame('por_aprobar', $liquidacion->fresh()->estatus);
    }

    public function test_solo_el_administrador_actualiza_la_configuracion_del_patrono(): void
    {
        $patrono = $this->crearPatrono('J-11000005-1', 'Patrono configuración');
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $datos = [
            'dias_bonificacion' => 45,
            'deposito_tipo' => 'fideicomiso',
            'anticipos_max_anio' => 3,
            'anticipos_pct_contabilidad' => 80,
            'anticipos_pct_fideicomiso' => 70,
            'anticipos_pct_fondo' => 60,
        ];

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('configuracion.update'), $datos)
            ->assertForbidden();

        $this->assertSame(30, (int) $patrono->fresh()->dias_bonificacion);

        $this->actingAs($administrador)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('configuracion.update'), $datos)
            ->assertRedirect(route('configuracion.index'));

        $this->assertSame(45, (int) $patrono->fresh()->dias_bonificacion);
    }

    private function crearLiquidacion(Patrono $patrono, Empleado $empleado): Liquidacion
    {
        return Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => '2026-10-01',
            'fecha_ingreso' => '2024-01-15',
            'fecha_egreso' => '2026-10-01',
            'motivo' => 'renuncia',
            'anios_servicio' => 2,
            'meses_fraccion' => 8,
            'dias_prestaciones' => 60,
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
        ]);
    }

    private function crearVacacion(Patrono $patrono, Empleado $empleado): Vacacion
    {
        return Vacacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_calculo' => '2026-10-01',
            'fecha_inicio' => '2026-10-15',
            'nro_vacacion' => 2,
            'periodo_desde' => '2025-01-15',
            'periodo_hasta' => '2026-01-15',
            'moneda' => 'USD',
        ]);
    }
}
