<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\Vacacion;
use App\Models\VacacionesRecibo;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurgarPapeleraTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_el_administrador_purga_definitivamente_un_registro(): void
    {
        $patrono = $this->crearPatrono('J-13000001-1', 'Patrono purga');
        $empleado = $this->crearEmpleado($patrono, 'V-13000001');
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);
        $empleado->delete();

        $this->actingAs($administrador)
            ->delete(route('papelera.purgar', ['tipo' => 'empleados', 'id' => $empleado->id]))
            ->assertRedirect(route('papelera.index', ['tipo' => 'empleados']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('empleados', ['id' => $empleado->id]);

        $this->assertDatabaseHas('historial_estados', [
            'auditable_type' => Empleado::class,
            'auditable_id' => $empleado->id,
            'campo' => 'papelera',
            'valor_anterior' => 'eliminado',
            'valor_nuevo' => 'purgado',
            'user_id' => $administrador->id,
            'patrono_id' => $patrono->id,
        ]);
    }

    public function test_un_analista_no_puede_purgar(): void
    {
        $patrono = $this->crearPatrono('J-13000002-1', 'Patrono purga');
        $empleado = $this->crearEmpleado($patrono, 'V-13000002');
        $analista = $this->crearUsuario(RolUsuario::Analista, [$patrono]);
        $empleado->delete();

        $this->actingAs($analista)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('papelera.purgar', ['tipo' => 'empleados', 'id' => $empleado->id]))
            ->assertForbidden();

        $this->assertSoftDeleted($empleado);
    }

    public function test_no_purga_un_patrono_con_trabajadores_en_la_papelera(): void
    {
        $patrono = $this->crearPatrono('J-13000003-1', 'Patrono purga');
        $empleado = $this->crearEmpleado($patrono, 'V-13000003');
        $administrador = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $empleado->delete();
        $patrono->delete();

        $this->actingAs($administrador)
            ->delete(route('papelera.purgar', ['tipo' => 'patronos', 'id' => $patrono->id]))
            ->assertSessionHas('error');

        $this->assertSoftDeleted($patrono);
        $this->assertSoftDeleted($empleado);
    }

    public function test_purga_un_patrono_sin_dependencias(): void
    {
        $patrono = $this->crearPatrono('J-13000009-1', 'Patrono purga');
        $administrador = $this->crearUsuario(RolUsuario::Administrador);

        $patrono->delete();

        $this->actingAs($administrador)
            ->delete(route('papelera.purgar', ['tipo' => 'patronos', 'id' => $patrono->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('patronos', ['id' => $patrono->id]);

        $this->assertDatabaseHas('historial_estados', [
            'auditable_type' => Patrono::class,
            'auditable_id' => $patrono->id,
            'campo' => 'papelera',
            'valor_nuevo' => 'purgado',
            'patrono_id' => null,
        ]);
    }

    public function test_no_purga_un_trabajador_con_documentos_eliminados(): void
    {
        $patrono = $this->crearPatrono('J-13000004-1', 'Patrono purga');
        $empleado = $this->crearEmpleado($patrono, 'V-13000004');
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

        $empleado->delete();

        $this->actingAs($administrador)
            ->delete(route('papelera.purgar', ['tipo' => 'empleados', 'id' => $empleado->id]))
            ->assertSessionHas('error');

        $this->assertSoftDeleted($empleado);
    }

    public function test_purgar_una_vacacion_arrastra_sus_recibos_eliminados(): void
    {
        $patrono = $this->crearPatrono('J-13000005-1', 'Patrono purga');
        $empleado = $this->crearEmpleado($patrono, 'V-13000005');
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
            ->delete(route('papelera.purgar', ['tipo' => 'vacaciones', 'id' => $vacacion->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vacaciones', ['id' => $vacacion->id]);
        $this->assertDatabaseMissing('vacaciones_recibos', ['id' => $recibo->id]);
    }

    public function test_la_purga_automatica_respeta_la_retencion(): void
    {
        $patrono = $this->crearPatrono('J-13000006-1', 'Patrono purga');
        $antiguo = $this->crearEmpleado($patrono, 'V-13000006');
        $reciente = $this->crearEmpleado($patrono, 'V-13000007');

        $antiguo->delete();
        $reciente->delete();

        DB::table('empleados')->where('id', $antiguo->id)->update(['deleted_at' => now()->subDays(120)]);
        DB::table('empleados')->where('id', $reciente->id)->update(['deleted_at' => now()->subDays(10)]);

        $this->artisan('papelera:purgar', [
            '--dias' => 90,
            '--tipo' => 'empleados',
            '--aplicar' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('empleados', ['id' => $antiguo->id]);
        $this->assertSoftDeleted($reciente);
    }

    public function test_la_simulacion_del_comando_no_borra_nada(): void
    {
        $patrono = $this->crearPatrono('J-13000008-1', 'Patrono purga');
        $empleado = $this->crearEmpleado($patrono, 'V-13000008');

        $empleado->delete();

        DB::table('empleados')->where('id', $empleado->id)->update(['deleted_at' => now()->subDays(120)]);

        $this->artisan('papelera:purgar', [
            '--dias' => 90,
            '--tipo' => 'empleados',
        ])->assertSuccessful();

        $this->assertSoftDeleted($empleado);
    }
}
