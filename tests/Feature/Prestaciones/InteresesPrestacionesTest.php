<?php

declare(strict_types=1);

namespace Tests\Feature\Prestaciones;

use App\Models\Empleado;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use App\Models\TasaBcv;
use App\Models\User;
use App\Services\PrestacionesSocialesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InteresesPrestacionesTest extends TestCase
{
    private const FECHA_ACTUAL = '2026-10-20';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::FECHA_ACTUAL.' 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_el_listado_aisla_los_pagos_por_patrono(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-11111111-1', 'V-11111111');
        [$otroPatrono, $otroEmpleado] = $this->crearPatronoEmpleado('J-22222222-2', 'V-22222222');

        $this->crearPago($patrono, $empleado, 'PAGO-PROPIO');
        $this->crearPago($otroPatrono, $otroEmpleado, 'PAGO-AJENO');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.index'))
            ->assertOk()
            ->assertSee('PAGO-PROPIO')
            ->assertDontSee('PAGO-AJENO');
    }

    public function test_el_listado_filtra_por_empleado_y_rango_de_fechas(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-33333333-3', 'V-33333333');
        $otroEmpleado = $this->crearEmpleado($patrono, 'V-44444444');

        $this->crearPago($patrono, $empleado, 'PAGO-OCTUBRE', '2026-10-10');
        $this->crearPago($patrono, $empleado, 'PAGO-AGOSTO', '2026-08-10');
        $this->crearPago($patrono, $otroEmpleado, 'PAGO-OTRO-EMPLEADO', '2026-10-11');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.index', [
                'empleado_id' => $empleado->id,
                'desde' => '2026-10-01',
                'hasta' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('PAGO-OCTUBRE')
            ->assertDontSee('PAGO-AGOSTO')
            ->assertDontSee('PAGO-OTRO-EMPLEADO')
            ->assertSee('Total pagado en USD')
            ->assertViewHas('totales', fn ($totales) => (float) $totales['USD'] === 10.0);
    }

    public function test_registrar_un_pago_recalcula_las_prestaciones(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-55555555-5', 'V-55555555');

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('intereses-prestaciones.store'), [
                'empleado_id' => $empleado->id,
                'fecha' => '2026-10-10',
                'monto' => '10,00',
                'moneda' => 'USD',
                'comprobante' => 'REC-001',
                'observacion' => 'Pago parcial',
            ])
            ->assertRedirect(route('intereses-prestaciones.index', ['empleado_id' => $empleado->id]));

        $this->assertDatabaseHas('pagos_interes_prestaciones', [
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'comprobante' => 'REC-001',
            'monto' => 10.00,
            'moneda' => 'USD',
        ]);

        // El pago del 10/10 cae en el período 15/09–14/10 (clave 2026-09).
        $this->assertEqualsWithDelta(
            10.0,
            (float) PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('periodo', '2026-09')
                ->value('interes_pagado'),
            0.01
        );
    }

    public function test_eliminar_un_pago_recalcula_las_prestaciones(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-66666666-6', 'V-66666666');

        $pago = $this->crearPago($patrono, $empleado, 'REC-002');
        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $this->assertEqualsWithDelta(
            10.0,
            (float) PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('periodo', '2026-09')
                ->value('interes_pagado'),
            0.01
        );

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->from(route('intereses-prestaciones.index'))
            ->delete(route('intereses-prestaciones.destroy', $pago))
            ->assertRedirect(route('intereses-prestaciones.index'));

        $this->assertSoftDeleted('pagos_interes_prestaciones', ['id' => $pago->id]);
        $this->assertEqualsWithDelta(
            0.0,
            (float) PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('periodo', '2026-09')
                ->value('interes_pagado'),
            0.01
        );
    }

    public function test_no_permite_registrar_pago_para_empleado_de_otro_patrono(): void
    {
        [$patrono] = $this->crearPatronoEmpleado('J-77777777-7', 'V-77777777');
        [$otroPatrono, $otroEmpleado] = $this->crearPatronoEmpleado('J-88888888-8', 'V-88888888');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('intereses-prestaciones.store'), [
                'empleado_id' => $otroEmpleado->id,
                'fecha' => '2026-10-10',
                'monto' => '10',
                'moneda' => 'USD',
            ])
            ->assertSessionHasErrors('empleado_id');

        $this->assertDatabaseCount('pagos_interes_prestaciones', 0);
    }

    public function test_no_permite_registrar_pago_para_empleado_inactivo(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-99999999-9', 'V-99999999', 'Inactivo');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('intereses-prestaciones.store'), [
                'empleado_id' => $empleado->id,
                'fecha' => '2026-10-10',
                'monto' => '10',
                'moneda' => 'USD',
            ])
            ->assertStatus(409);
    }

    public function test_la_pantalla_de_crear_movimiento_ya_no_incluye_el_panel(): void
    {
        [$patrono] = $this->crearPatronoEmpleado('J-10101010-1', 'V-10101010');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('movimientos-prestaciones.create'))
            ->assertOk()
            ->assertDontSee('pagos-intereses-panel')
            ->assertDontSee('Seleccione un trabajador para registrar pagos de intereses');

        $this->assertTrue(Route::has('intereses-prestaciones.index'));
        $this->assertFalse(Route::has('movimientos-prestaciones.intereses.panel'));
        $this->assertFalse(Route::has('movimientos-prestaciones.intereses.prestaciones'));
    }

    public function test_la_pantalla_de_crear_movimiento_muestra_el_panel_de_saldos_como_intereses(): void
    {
        [$patrono] = $this->crearPatronoEmpleado('J-24242424-4', 'V-24242424');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('movimientos-prestaciones.create'))
            ->assertOk()
            ->assertSee('id="info-saldo"', false)
            ->assertSee('name="fecha_comprobante"', false)
            ->assertSee('bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-900', false);
    }

    public function test_el_listado_ya_no_incluye_el_formulario_de_registro(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-12121212-1', 'V-12121212');
        $this->crearPago($patrono, $empleado, 'REC-LISTADO');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.index'))
            ->assertOk()
            ->assertSee('+ Nuevo pago')
            ->assertSee('REC-LISTADO')
            ->assertSee('title="Agregar Intereses sobre prest."', false)
            ->assertDontSee('Registrar pago de intereses');
    }

    public function test_el_aviso_de_inactivo_solo_aparece_para_trabajadores_no_activos(): void
    {
        [$patrono, $activo] = $this->crearPatronoEmpleado('J-23232323-1', 'V-23232323');
        $inactivo = $this->crearEmpleado($patrono, 'V-23232324', 'Inactivo');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.index', ['empleado_id' => $activo->id]))
            ->assertOk()
            ->assertDontSee('trabajador inactivo o liquidado');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.index', ['empleado_id' => $inactivo->id]))
            ->assertOk()
            ->assertSee('trabajador inactivo o liquidado');
    }

    public function test_la_pantalla_de_creacion_solo_ofrece_trabajadores_activos(): void
    {
        [$patrono, $activo] = $this->crearPatronoEmpleado('J-13131313-1', 'V-13131313');
        $inactivo = $this->crearEmpleado($patrono, 'V-14141414', 'Inactivo');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.create'))
            ->assertOk()
            ->assertSee('V-13131313')
            ->assertDontSee('V-14141414')
            ->assertSee(route('intereses-prestaciones.store'));
    }

    public function test_la_pantalla_de_creacion_preselecciona_el_trabajador(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-15151515-1', 'V-15151515');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.create', ['empleado_id' => $empleado->id]))
            ->assertOk()
            ->assertSee('V-15151515')
            ->assertSee('value="'.$empleado->id.'" selected', false);
    }

    public function test_no_permite_abrir_el_formulario_para_un_trabajador_inactivo(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-16161616-1', 'V-16161616', 'Inactivo');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.create', ['empleado_id' => $empleado->id]))
            ->assertStatus(409);
    }

    public function test_el_saldo_de_intereses_se_muestra_en_usd_y_bs_segun_la_fecha(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-17171717-1', 'V-17171717');
        TasaBcv::create(['fecha' => '2026-10-20', 'tasa_usd' => 100, 'fuente' => 'manual']);
        $this->crearRegistroPrestacion($patrono, $empleado, '2026-09', 'USD', 12.34);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->getJson(route('intereses-prestaciones.saldo', [
                'empleadoId' => $empleado->id,
                'fecha' => '2026-10-20',
            ]))
            ->assertOk()
            ->assertJson([
                'fecha' => '20/10/2026',
                'periodo' => '2026-09',
                'moneda' => 'USD',
                'pendiente' => 12.34,
                'tasa' => 100,
                'usd' => 12.34,
                'bs' => 1234,
            ]);
    }

    public function test_el_saldo_de_intereses_en_bs_se_convierte_a_usd_con_la_tasa(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-18181818-1', 'V-18181818');
        $empleado->update(['moneda' => 'BS']);
        TasaBcv::create(['fecha' => '2026-10-01', 'tasa_usd' => 100, 'fuente' => 'manual']);
        $this->crearRegistroPrestacion($patrono, $empleado->refresh(), '2026-09', 'BS', 500);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->getJson(route('intereses-prestaciones.saldo', [
                'empleadoId' => $empleado->id,
                'fecha' => '2026-10-20',
            ]))
            ->assertOk()
            ->assertJson([
                'periodo' => '2026-09',
                'moneda' => 'BS',
                'pendiente' => 500,
                'tasa' => 100,
                'usd' => 5,
                'bs' => 500,
            ]);
    }

    public function test_el_saldo_de_intereses_a_fecha_futura_incluye_la_proyeccion(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-25252525-2', 'V-25252525');
        TasaBcv::create(['fecha' => '2026-10-20', 'tasa_usd' => 100, 'fuente' => 'manual']);

        PrestacionSocial::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'periodo' => '2026-12',
            'fecha_cierre' => '2026-12-31',
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
            'capital_final' => 1000,
            'interes_pendiente_final' => 12.34,
            'saldo_final' => 1012.34,
            'es_proyeccion' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->getJson(route('intereses-prestaciones.saldo', [
                'empleadoId' => $empleado->id,
                'fecha' => '2026-12-31',
            ]))
            ->assertOk()
            ->assertJson([
                'fecha' => '31/12/2026',
                'periodo' => '2026-12',
                'moneda' => 'USD',
                'pendiente' => 12.34,
                'tasa' => 100,
                'usd' => 12.34,
                'bs' => 1234,
                'es_proyeccion' => true,
            ]);
    }

    public function test_registrar_un_pago_futuro_actualiza_la_proyeccion_y_guarda_el_comprobante(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-26262626-2', 'V-26262626');

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('intereses-prestaciones.store'), [
                'empleado_id' => $empleado->id,
                'fecha' => '2026-12-20',
                'fecha_comprobante' => '2026-10-20',
                'monto' => '10,00',
                'moneda' => 'USD',
                'comprobante' => 'REC-FUTURO',
            ])
            ->assertRedirect(route('intereses-prestaciones.index', ['empleado_id' => $empleado->id]));

        $this->assertDatabaseHas('pagos_interes_prestaciones', [
            'empleado_id' => $empleado->id,
            'fecha' => '2026-12-20',
            'fecha_comprobante' => '2026-10-20',
            'comprobante' => 'REC-FUTURO',
            'monto' => 10.00,
        ]);

        $diciembre = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-12')
            ->first();

        $this->assertNotNull($diciembre);
        $this->assertTrue((bool) $diciembre->es_proyeccion);
        $this->assertEqualsWithDelta(10.0, (float) $diciembre->interes_pagado, 0.01);
        $this->assertSame(
            '2026-12',
            PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('es_proyeccion', true)
                ->max('periodo')
        );
    }

    public function test_el_saldo_de_intereses_sin_periodos_devuelve_cero(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-19191919-1', 'V-19191919');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->getJson(route('intereses-prestaciones.saldo', [
                'empleadoId' => $empleado->id,
                'fecha' => '2026-10-20',
            ]))
            ->assertOk()
            ->assertJson([
                'periodo' => null,
                'moneda' => 'USD',
                'pendiente' => 0,
                'tasa' => null,
                'usd' => 0,
                'bs' => null,
            ]);
    }

    public function test_el_saldo_de_intereses_no_se_muestra_para_empleado_inactivo(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-20202020-1', 'V-20202020', 'Inactivo');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->getJson(route('intereses-prestaciones.saldo', [
                'empleadoId' => $empleado->id,
                'fecha' => '2026-10-20',
            ]))
            ->assertStatus(409);
    }

    public function test_el_formulario_de_creacion_incluye_el_panel_de_saldos(): void
    {
        [$patrono] = $this->crearPatronoEmpleado('J-21212121-1', 'V-21212121');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.create'))
            ->assertOk()
            ->assertSee('id="info-intereses"', false)
            ->assertSee('name="fecha_comprobante"', false)
            ->assertSee('intereses-prestaciones/saldo/__EMPLEADO__', false);
    }

    public function test_el_estado_de_cuenta_muestra_los_intereses_pagados_en_negativo(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-23232323-3', 'V-23232323');

        $this->crearPago($patrono, $empleado, 'PAGO-NEGATIVO');
        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.show', $empleado->id))
            ->assertOk()
            ->assertSee('<td class="px-3 py-2 text-right text-red-600">- 10,00</td>', false);
    }

    public function test_el_listado_enlaza_al_detalle_de_cada_pago(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-27272727-2', 'V-27272727');
        $pago = $this->crearPago($patrono, $empleado, 'REC-ENLACE');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.index'))
            ->assertOk()
            ->assertSee(route('intereses-prestaciones.show', $pago), false);
    }

    public function test_el_detalle_del_pago_muestra_los_datos_y_el_acceso_al_comprobante(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-28282828-2', 'V-28282828');
        $pago = $this->crearPago($patrono, $empleado, 'REC-DETALLE');
        $pago->update(['fecha_comprobante' => '2026-10-21', 'tasa_cambio' => 100, 'observacion' => 'Pago de octubre']);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.show', $pago))
            ->assertOk()
            ->assertSee('Pago de Intereses sobre Prestaciones')
            ->assertSee('REC-DETALLE')
            ->assertSee('Pago de octubre')
            ->assertSee($pago->numeroComprobante())
            ->assertSee('Bs. 1.000,00')
            ->assertSee(route('intereses-prestaciones.comprobante', $pago), false);
    }

    public function test_el_comprobante_del_pago_se_genera_en_pdf(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-29292929-2', 'V-29292929');
        $pago = $this->crearPago($patrono, $empleado, 'REC-PDF');

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.comprobante', $pago));

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $respuesta->headers->get('content-type'));
    }

    public function test_eliminar_desde_el_detalle_redirige_al_listado(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado('J-33333333-4', 'V-33333334');
        $pago = $this->crearPago($patrono, $empleado, 'REC-DESDE-DETALLE');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->from(route('intereses-prestaciones.show', $pago))
            ->delete(route('intereses-prestaciones.destroy', $pago), ['volver_a' => 'index'])
            ->assertRedirect(route('intereses-prestaciones.index'));

        $this->assertSoftDeleted('pagos_interes_prestaciones', ['id' => $pago->id]);
    }

    public function test_no_permite_ver_ni_imprimir_un_pago_de_otro_patrono(): void
    {
        [$patrono] = $this->crearPatronoEmpleado('J-30303030-3', 'V-30303030');
        [$otroPatrono, $otroEmpleado] = $this->crearPatronoEmpleado('J-31313131-3', 'V-31313131');
        $pagoAjeno = $this->crearPago($otroPatrono, $otroEmpleado, 'REC-AJENO');

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.show', $pagoAjeno))
            ->assertNotFound();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('intereses-prestaciones.comprobante', $pagoAjeno))
            ->assertNotFound();
    }

    /**
     * @return array{0:Patrono, 1:Empleado}
     */
    private function crearPatronoEmpleado(string $rif, string $cedula, string $estatus = 'Activo'): array
    {
        $patrono = Patrono::create([
            'rif' => $rif,
            'razon_social' => 'Patrono '.$rif,
            'estado' => 'Activo',
            'dias_bonificacion' => 30,
            'deposito_tipo' => 'contabilidad',
            'anticipos_max_anio' => 2,
            'anticipos_pct_contabilidad' => 100,
        ]);

        return [$patrono, $this->crearEmpleado($patrono, $cedula, $estatus)];
    }

    private function crearEmpleado(Patrono $patrono, string $cedula, string $estatus = 'Activo'): Empleado
    {
        return Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'cedula' => $cedula,
            'fecha_ingreso' => '2024-01-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'moneda' => 'USD',
            'estatus' => $estatus,
        ]);
    }

    private function crearPago(
        Patrono $patrono,
        Empleado $empleado,
        string $comprobante,
        string $fecha = '2026-10-10',
        float $monto = 10.0,
    ): PagoInteresPrestaciones {
        return PagoInteresPrestaciones::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha' => $fecha,
            'monto' => $monto,
            'moneda' => 'USD',
            'comprobante' => $comprobante,
        ]);
    }

    private function crearRegistroPrestacion(
        Patrono $patrono,
        Empleado $empleado,
        string $periodo,
        string $moneda,
        float $interesPendiente,
    ): PrestacionSocial {
        return PrestacionSocial::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'periodo' => $periodo,
            'fecha_cierre' => Carbon::createFromFormat('Y-m', $periodo)->endOfMonth()->toDateString(),
            'salario_integral_diario' => 10,
            'moneda' => $moneda,
            'capital_final' => 1000,
            'interes_pendiente_final' => $interesPendiente,
            'saldo_final' => 1000 + $interesPendiente,
        ]);
    }
}
