<?php

declare(strict_types=1);

namespace Tests\Feature\Auditoria;

use App\Models\BonificacionFinAno;
use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use App\Models\Patrono;
use App\Models\User;
use App\Models\Vacacion;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    public function test_registra_quien_crea_y_el_estado_inicial(): void
    {
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario);

        [$patrono, $empleado] = $this->crearEscenario();
        $liquidacion = $this->crearLiquidacion($patrono, $empleado);

        $this->assertSame($usuario->id, $patrono->created_by);
        $this->assertSame($usuario->id, $empleado->created_by);
        $this->assertSame($usuario->id, $liquidacion->created_by);
        $this->assertSame($usuario->id, $liquidacion->updated_by);

        $creacion = $liquidacion->historialEstados()->first();

        $this->assertNotNull($creacion);
        $this->assertSame('estatus', $creacion->campo);
        $this->assertNull($creacion->valor_anterior);
        $this->assertSame('por_aprobar', $creacion->valor_nuevo);
        $this->assertSame($usuario->id, $creacion->user_id);
        $this->assertSame($patrono->id, $creacion->patrono_id);
    }

    public function test_sella_aprobacion_y_anulacion_de_liquidaciones(): void
    {
        $creador = User::factory()->administrador()->create(['name' => 'Creador Uno']);
        $aprobador = User::factory()->administrador()->create(['name' => 'Aprobador Dos']);
        $anulador = User::factory()->administrador()->create(['name' => 'Anulador Tres']);

        $this->actingAs($creador);
        [$patrono, $empleado] = $this->crearEscenario();
        $liquidacion = $this->crearLiquidacion($patrono, $empleado);

        $this->actingAs($aprobador);
        $liquidacion->update(['estatus' => 'aprobada']);
        $liquidacion->refresh();

        $this->assertSame($aprobador->id, $liquidacion->approved_by);
        $this->assertNotNull($liquidacion->approved_at);
        $this->assertSame($aprobador->id, $liquidacion->updated_by);
        $this->assertSame($creador->id, $liquidacion->created_by);

        $this->actingAs($anulador);
        $liquidacion->update(['estatus' => 'anulada']);
        $liquidacion->refresh();

        $this->assertSame($anulador->id, $liquidacion->annulled_by);
        $this->assertNotNull($liquidacion->annulled_at);
        $this->assertSame($aprobador->id, $liquidacion->approved_by);

        $historial = $liquidacion->historialEstados()->reorder('id')->get();

        $this->assertSame(
            [null, 'por_aprobar', 'aprobada'],
            $historial->pluck('valor_anterior')->all()
        );
        $this->assertSame(
            ['por_aprobar', 'aprobada', 'anulada'],
            $historial->pluck('valor_nuevo')->all()
        );
        $this->assertSame(
            [$creador->id, $aprobador->id, $anulador->id],
            $historial->pluck('user_id')->all()
        );
    }

    public function test_sella_aprobacion_y_anulacion_de_vacaciones(): void
    {
        $aprobador = User::factory()->administrador()->create();
        $anulador = User::factory()->administrador()->create();

        $this->actingAs($aprobador);
        [$patrono, $empleado] = $this->crearEscenario();
        $vacacion = $this->crearVacacion($patrono, $empleado);

        $vacacion->update(['estatus_calculo' => 'aprobada']);
        $vacacion->refresh();

        $this->assertSame($aprobador->id, $vacacion->approved_by);
        $this->assertNotNull($vacacion->approved_at);

        $this->actingAs($anulador);
        $vacacion->update(['estatus_calculo' => 'no_aprobada']);
        $vacacion->refresh();

        $this->assertSame($anulador->id, $vacacion->annulled_by);
        $this->assertNotNull($vacacion->annulled_at);
        $this->assertSame($aprobador->id, $vacacion->approved_by);

        $historial = $vacacion->historialEstados()
            ->where('campo', 'estatus_calculo')
            ->reorder('id')
            ->get();

        $this->assertSame(
            ['por_aprobar', 'aprobada', 'no_aprobada'],
            $historial->pluck('valor_nuevo')->all()
        );
    }

    public function test_aprobar_bonificacion_sella_y_recalcular_limpia_el_sello(): void
    {
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario);
        [$patrono, $empleado] = $this->crearEscenario();
        $bonificacion = $this->crearBonificacion($patrono, $empleado);

        $bonificacion->update([
            'estatus' => 'aprobado',
            'fecha_aprobacion' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
        ]);
        $bonificacion->refresh();

        $this->assertSame($usuario->id, $bonificacion->approved_by);
        $this->assertNotNull($bonificacion->approved_at);

        $bonificacion->update(['estatus' => 'calculado']);
        $bonificacion->refresh();

        $this->assertNull($bonificacion->approved_by);
        $this->assertNull($bonificacion->approved_at);
        $this->assertNull($bonificacion->fecha_aprobacion);
        $this->assertNull($bonificacion->fecha_emision);

        $historial = $bonificacion->historialEstados()->reorder('id')->get();

        $this->assertSame(
            ['calculado', 'aprobado', 'calculado'],
            $historial->pluck('valor_nuevo')->all()
        );
    }

    public function test_anular_movimiento_sella_usuario_y_fecha(): void
    {
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario);
        [$patrono, $empleado] = $this->crearEscenario();
        $movimiento = $this->crearMovimiento($patrono, $empleado);

        $movimiento->update(['estatus' => 'anulado']);
        $movimiento->refresh();

        $this->assertSame($usuario->id, $movimiento->annulled_by);
        $this->assertNotNull($movimiento->annulled_at);

        $historial = $movimiento->historialEstados()->reorder('id')->get();

        $this->assertSame(
            ['activo', 'anulado'],
            $historial->pluck('valor_nuevo')->all()
        );
    }

    public function test_la_vista_de_liquidacion_muestra_auditoria_e_historial(): void
    {
        $creador = User::factory()->administrador()->create(['name' => 'Creador Uno']);
        $aprobador = User::factory()->administrador()->create(['name' => 'Aprobador Dos']);

        $this->actingAs($creador);
        [$patrono, $empleado] = $this->crearEscenario();
        $liquidacion = $this->crearLiquidacion($patrono, $empleado);

        $this->actingAs($aprobador);
        $liquidacion->update(['estatus' => 'aprobada']);

        $this->withSession(['patrono_id' => $patrono->id])
            ->get(route('liquidaciones.show', $liquidacion->id))
            ->assertOk()
            ->assertSee('Auditoría')
            ->assertSee('Historial de estados')
            ->assertSee('Creador Uno')
            ->assertSee('Aprobador Dos')
            ->assertSee('Por aprobar')
            ->assertSee('Aprobada');
    }

    public function test_las_vistas_de_detalle_muestran_la_auditoria(): void
    {
        $usuario = User::factory()->administrador()->create();

        $this->actingAs($usuario);
        [$patrono, $empleado] = $this->crearEscenario();

        $liquidacion = $this->crearLiquidacion($patrono, $empleado);
        $vacacion = $this->crearVacacion($patrono, $empleado);
        $bonificacion = $this->crearBonificacion($patrono, $empleado);
        $movimiento = $this->crearMovimiento($patrono, $empleado);

        $rutas = [
            route('liquidaciones.show', $liquidacion->id),
            route('vacaciones.show', $vacacion),
            route('utilidades.show', $bonificacion->id),
            route('movimientos-prestaciones.show', $movimiento),
        ];

        foreach ($rutas as $url) {
            $this->withSession(['patrono_id' => $patrono->id])
                ->get($url)
                ->assertOk()
                ->assertSee('Auditoría')
                ->assertSee('Historial de estados');
        }
    }

    /**
     * @return array{0: Patrono, 1: Empleado}
     */
    private function crearEscenario(): array
    {
        $patrono = Patrono::create([
            'rif' => 'J-31313131-3',
            'razon_social' => 'Patrono de auditoría',
            'estado' => 'Activo',
            'dias_bonificacion' => 30,
            'deposito_tipo' => 'contabilidad',
            'anticipos_max_anio' => 2,
            'anticipos_pct_contabilidad' => 100,
        ]);

        $empleado = Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'cedula' => 'V-31313131',
            'fecha_ingreso' => '2024-01-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'salario_base' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);

        return [$patrono, $empleado];
    }

    private function crearLiquidacion(Patrono $patrono, Empleado $empleado): Liquidacion
    {
        return Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => '2026-10-01',
            'fecha_ingreso' => '2024-01-15',
            'fecha_egreso' => '2026-10-01',
            'motivo' => 'retiro_voluntario',
            'anios_servicio' => 2,
            'meses_fraccion' => 8,
            'dias_prestaciones' => 60,
            'salario_integral_diario' => 20,
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
            'nro_vacacion' => 1,
            'periodo_desde' => '2025-01-15',
            'periodo_hasta' => '2026-01-14',
            'salario_normal' => 300,
            'moneda' => 'USD',
        ]);
    }

    private function crearBonificacion(Patrono $patrono, Empleado $empleado): BonificacionFinAno
    {
        return BonificacionFinAno::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'anio' => 2026,
            'periodo_inicio' => '2026-01-01',
            'periodo_fin' => '2026-12-31',
            'asignaciones' => [],
            'deducciones' => [],
            'moneda' => 'USD',
            'estatus' => 'calculado',
        ]);
    }

    private function crearMovimiento(Patrono $patrono, Empleado $empleado): MovimientoPrestaciones
    {
        return MovimientoPrestaciones::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'tipo' => MovimientoPrestaciones::TIPO_ANTICIPO,
            'fecha' => '2026-10-01',
            'concepto' => 'Anticipo de prueba',
            'motivo' => 'salud',
            'monto' => 100,
            'moneda' => 'USD',
        ]);
    }
}
