<?php

declare(strict_types=1);

namespace Tests\Feature\Prestaciones;

use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use App\Models\TasaInteresPrestaciones;
use App\Models\User;
use App\Services\MovimientosPrestacionesService;
use App\Services\PrestacionesSocialesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PrestacionesSocialesTest extends TestCase
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

    public function test_un_anticipo_recalcula_la_garantia_y_anularlo_la_restaura(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $movimientos = app(MovimientosPrestacionesService::class);

        $prestaciones->regenerar($empleado);
        $saldoAntes = $prestaciones->capitalEn($empleado, Carbon::parse('2026-10-31'));
        $this->assertGreaterThan(0, $saldoAntes);

        $movimiento = $movimientos->crear($empleado, [
            'tipo' => MovimientoPrestaciones::TIPO_ANTICIPO,
            'fecha' => Carbon::parse('2026-10-10'),
            'motivo' => 'salud',
            'monto' => 10.0,
            'moneda' => 'USD',
        ]);

        $saldoDespues = $prestaciones->capitalEn($empleado, Carbon::parse('2026-10-31'));

        $this->assertEqualsWithDelta(10.0, $saldoAntes - $saldoDespues, 0.01);
        // El 10/10 cae en el período 15/09–14/10, cuya clave es 2026-09.
        $this->assertEqualsWithDelta(
            10.0,
            (float) PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('periodo', '2026-09')
                ->value('anticipos'),
            0.01
        );

        $movimientos->anular($movimiento);

        $this->assertEqualsWithDelta(
            $saldoAntes,
            $prestaciones->capitalEn($empleado, Carbon::parse('2026-10-31')),
            0.01
        );
    }

    public function test_eliminar_un_anticipo_anulado_recalcula_la_garantia(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $movimientos = app(MovimientosPrestacionesService::class);

        $prestaciones->regenerar($empleado);
        $saldoAntes = $prestaciones->capitalEn($empleado, Carbon::parse('2026-10-31'));

        $movimiento = $movimientos->crear($empleado, [
            'tipo' => MovimientoPrestaciones::TIPO_ANTICIPO,
            'fecha' => Carbon::parse('2026-10-10'),
            'motivo' => 'vivienda',
            'monto' => 10.0,
            'moneda' => 'USD',
        ]);

        // Simula un movimiento anulado sin recálculo (dato legado): la
        // garantía sigue descontando un anticipo que ya no está vigente.
        $movimiento->update(['estatus' => 'anulado']);

        $this->assertEqualsWithDelta(
            $saldoAntes - 10.0,
            $prestaciones->capitalEn($empleado, Carbon::parse('2026-10-31')),
            0.01
        );

        $movimientos->eliminar($movimiento);

        $this->assertSoftDeleted('movimientos_prestaciones', ['id' => $movimiento->id]);
        $this->assertEqualsWithDelta(
            $saldoAntes,
            $prestaciones->capitalEn($empleado, Carbon::parse('2026-10-31')),
            0.01
        );
    }

    public function test_las_consultas_no_generan_periodos_de_prestaciones(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $movimientos = app(MovimientosPrestacionesService::class);

        $movimientos->maximoAnticipo($empleado, Carbon::parse('2026-10-10'));
        $prestaciones->estadoDeCuenta($empleado);
        $prestaciones->saldoEn($empleado);

        $this->assertSame(
            0,
            PrestacionSocial::where('empleado_id', $empleado->id)->count(),
            'Las consultas GET no deben crear períodos de prestaciones.'
        );
    }

    public function test_el_maximo_anticipo_a_fecha_futura_incluye_las_proyecciones(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $movimientos = app(MovimientosPrestacionesService::class);

        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00'));
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));
        $fecha = Carbon::parse('2026-12-31');

        $proyectado = $prestaciones->registroEn($empleado, $fecha, true);
        $max = $movimientos->maximoAnticipo($empleado, $fecha);

        $this->assertNotNull($proyectado);
        $this->assertTrue((bool) $proyectado->es_proyeccion);
        $this->assertEqualsWithDelta((float) $proyectado->capital_final, $max['saldo'], 0.01);
        $this->assertTrue($max['es_proyeccion']);
    }

    public function test_un_anticipo_futuro_se_refleja_en_la_proyeccion_sin_eliminarla(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $movimientos = app(MovimientosPrestacionesService::class);

        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        $antes = (float) PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-12')
            ->value('capital_final');

        $movimiento = $movimientos->crear($empleado, [
            'tipo' => MovimientoPrestaciones::TIPO_ANTICIPO,
            'fecha' => Carbon::parse('2026-12-15'),
            'fecha_comprobante' => Carbon::parse('2026-10-20'),
            'motivo' => 'salud',
            'monto' => 10.0,
            'moneda' => 'USD',
        ]);

        $diciembre = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-12')
            ->first();

        $this->assertNotNull($diciembre);
        $this->assertTrue((bool) $diciembre->es_proyeccion);
        $this->assertEqualsWithDelta(10.0, (float) $diciembre->anticipos, 0.01);
        $this->assertEqualsWithDelta($antes - 10.0, (float) $diciembre->capital_final, 0.01);
        $this->assertSame(
            '2026-12',
            PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('es_proyeccion', true)
                ->max('periodo')
        );
        $this->assertSame('2026-10-20', $movimiento->fecha_comprobante->toDateString());
    }

    public function test_el_movimiento_guarda_la_fecha_del_comprobante(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('movimientos-prestaciones.store'), [
                'empleado_id' => $empleado->id,
                'tipo' => 'anticipo',
                'fecha' => '2026-12-15',
                'fecha_comprobante' => '2026-10-20',
                'motivo' => 'salud',
                'monto' => '10',
                'moneda' => 'USD',
            ])
            ->assertSessionHasNoErrors();

        $movimiento = MovimientoPrestaciones::latest('id')->first();

        $this->assertSame('2026-12-15', $movimiento->fecha->toDateString());
        $this->assertSame('2026-10-20', $movimiento->fecha_comprobante->toDateString());
    }

    public function test_el_movimiento_rechaza_fechas_mas_alla_del_horizonte_de_proyeccion(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('movimientos-prestaciones.store'), [
                'empleado_id' => $empleado->id,
                'tipo' => 'anticipo',
                'fecha' => '2028-01-15',
                'motivo' => 'salud',
                'monto' => '10',
                'moneda' => 'USD',
            ])
            ->assertSessionHasErrors('fecha');
    }

    public function test_los_periodos_se_detienen_en_la_fecha_de_egreso(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);

        Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => '2025-06-30',
            'fecha_ingreso' => '2024-01-15',
            'fecha_egreso' => '2025-06-30',
            'motivo' => 'retiro_voluntario',
            'anios_servicio' => 1,
            'meses_fraccion' => 5,
            'dias_prestaciones' => 35,
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
            'total_asignaciones' => 0,
            'total_deducciones' => 0,
            'neto' => 0,
            'estatus' => 'aprobada',
        ]);

        $requeridos = $prestaciones->periodosRequeridos($empleado, Carbon::parse('2026-12-31'));
        $this->assertSame('2025-06', end($requeridos));

        $prestaciones->asegurarPeriodos($empleado, Carbon::parse('2026-12-31'));

        $this->assertSame(
            '2025-06',
            PrestacionSocial::where('empleado_id', $empleado->id)->max('periodo')
        );

        $cuenta = $prestaciones->estadoDeCuenta($empleado);
        $this->assertSame('2025-06', $cuenta->last()?->periodo);

        $this->assertEqualsWithDelta(
            (float) PrestacionSocial::where('empleado_id', $empleado->id)
                ->where('periodo', '2025-06')
                ->value('capital_final'),
            $prestaciones->capitalEn($empleado, Carbon::parse('2026-10-31')),
            0.01
        );
    }

    public function test_el_cierre_por_defecto_excluye_las_proyecciones(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);
        $creados = $prestaciones->proyectarHasta($empleado, '2026-12');

        $this->assertSame(2, $creados);

        $hasta = $prestaciones->ultimoPeriodoReal($empleado);
        $this->assertSame('2026-10', $hasta);

        $rango = $prestaciones->estadoDeCuentaEnRango($empleado, null, $hasta);
        $this->assertSame('2026-10', $rango['hasta']);
        $this->assertFalse(
            $rango['filas']->contains(fn ($fila) => (bool) $fila->es_proyeccion)
        );

        $rangoConProyeccion = $prestaciones->estadoDeCuentaEnRango($empleado, null, '2026-12');
        $this->assertTrue(
            $rangoConProyeccion['filas']->contains(fn ($fila) => (bool) $fila->es_proyeccion)
        );
    }

    public function test_la_pantalla_muestra_las_proyecciones_por_defecto(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.show', $empleado->id))
            ->assertOk()
            ->assertViewHas('periodoHasta', '2026-12')
            ->assertViewHas('incluyeProyeccion', true)
            ->assertViewHas(
                'cuenta',
                fn ($cuenta) => $cuenta->contains(fn ($fila) => (bool) $fila->es_proyeccion)
            );
    }

    public function test_la_proyeccion_del_listado_se_aplica_solo_a_los_trabajadores_activos(): void
    {
        [$patrono, $activo] = $this->crearEmpleado();

        $inactivo = Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Luis',
            'apellidos' => 'Gómez',
            'cedula' => 'V-87654321',
            'fecha_ingreso' => '2024-01-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'moneda' => 'USD',
            'estatus' => 'Inactivo',
        ]);

        app(PrestacionesSocialesService::class)->regenerar($inactivo);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('prestaciones.proyectar'), ['hasta' => '2026-12'])
            ->assertRedirect(route('prestaciones.index'));

        $this->assertSame(2, PrestacionSocial::where('empleado_id', $activo->id)
            ->where('es_proyeccion', true)
            ->count());
        $this->assertSame('2026-12', PrestacionSocial::where('empleado_id', $activo->id)
            ->where('es_proyeccion', true)
            ->max('periodo'));
        $this->assertSame(0, PrestacionSocial::where('empleado_id', $inactivo->id)
            ->where('es_proyeccion', true)
            ->count());
    }

    public function test_el_listado_muestra_la_columna_calculado_hasta_con_el_ultimo_periodo(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        $cierre = $prestaciones->ultimoCierreConProyeccion($empleado);
        $this->assertNotNull($cierre);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.index'))
            ->assertOk()
            ->assertSeeInOrder(['>Estatus</th>', '>Calculado hasta</th>', '>Saldo prestaciones</th>'], false)
            ->assertSee($cierre->format('d/m/Y'));
    }

    public function test_el_formulario_de_proyeccion_se_ubica_en_el_listado_y_no_en_el_detalle(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.index'))
            ->assertOk()
            ->assertSee('Proyectar / calcular hasta')
            ->assertSee('Se aplicará a los 1 trabajador(es) activo(s)');

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.show', $empleado->id))
            ->assertOk()
            ->assertDontSee('Proyectar / calcular hasta');
    }

    public function test_permite_eliminar_un_periodo_proyectado_individual(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        $noviembre = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-11')
            ->firstOrFail();

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.show', $empleado->id))
            ->assertOk()
            ->assertSee(route('prestaciones.proyeccion.periodo.destroy', [$empleado->id, $noviembre->id]), false);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('prestaciones.proyeccion.periodo.destroy', [$empleado->id, $noviembre->id]))
            ->assertRedirect(route('prestaciones.show', $empleado->id));

        $this->assertDatabaseMissing('prestaciones_sociales', ['id' => $noviembre->id]);
        $this->assertDatabaseHas('prestaciones_sociales', [
            'empleado_id' => $empleado->id,
            'periodo' => '2026-12',
            'es_proyeccion' => true,
        ]);
    }

    public function test_no_permite_eliminar_un_periodo_real_como_si_fuera_proyeccion(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $real = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', false)
            ->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('prestaciones.proyeccion.periodo.destroy', [$empleado->id, $real->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('prestaciones_sociales', ['id' => $real->id]);
    }

    public function test_la_proyeccion_del_listado_rechaza_un_mes_que_no_es_futuro(): void
    {
        [$patrono] = $this->crearEmpleado();

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->from(route('prestaciones.index'))
            ->post(route('prestaciones.proyectar'), ['hasta' => '2026-10'])
            ->assertSessionHasErrors('hasta');
    }

    public function test_el_calculo_real_reemplaza_la_proyeccion_del_mes_vencido(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);

        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00'));
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2027-01');

        Carbon::setTestNow(Carbon::parse('2026-12-20 12:00:00'));
        TasaInteresPrestaciones::create(['periodo' => '2026-12', 'tasa_interes' => 12, 'fuente' => 'manual']);

        $resultado = $prestaciones->asegurarPeriodos($empleado);
        $this->assertSame(3, $resultado['creados']);

        // Diciembre cierra el trimestre el 14/01: el recálculo real debe
        // aportar el depósito trimestral y el interés del mes.
        $vencido = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-12')
            ->first();

        $this->assertFalse((bool) $vencido->es_proyeccion);
        $this->assertGreaterThan(0, (float) $vencido->deposito_trimestre);
        $this->assertGreaterThan(0, (float) $vencido->interes_mes);

        // La proyección de un mes aún futuro se conserva.
        $this->assertTrue((bool) PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2027-01')
            ->value('es_proyeccion'));
    }

    public function test_el_interes_del_mes_se_calcula_sobre_el_capital_del_mes_anterior(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        TasaInteresPrestaciones::create([
            'periodo' => '2026-09',
            'tasa_interes' => 12,
            'fuente' => 'manual',
        ]);

        $prestaciones = app(PrestacionesSocialesService::class);
        $movimientos = app(MovimientosPrestacionesService::class);

        $prestaciones->regenerar($empleado);

        // El período 2026-09 cierra el 14/10 y abona el depósito trimestral.
        $septiembre = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-09')
            ->firstOrFail();

        $this->assertGreaterThan(0, (float) $septiembre->capital_inicial);
        $this->assertGreaterThan(0, (float) $septiembre->deposito_trimestre);

        $interesEsperado = round((float) $septiembre->capital_inicial * 12 / 100 / 12, 2);

        $this->assertEqualsWithDelta($interesEsperado, (float) $septiembre->interes_mes, 0.01);

        // No debe incluir el capital que se abona dentro del propio mes.
        $this->assertLessThan(
            round(
                ((float) $septiembre->capital_inicial + (float) $septiembre->deposito_trimestre) * 12 / 100 / 12,
                2
            ),
            (float) $septiembre->interes_mes
        );

        // Un anticipo del mismo período reduce el capital del mes, pero no
        // altera el interés ya determinado por el capital del mes anterior.
        $movimientos->crear($empleado, [
            'tipo' => MovimientoPrestaciones::TIPO_ANTICIPO,
            'fecha' => Carbon::parse('2026-10-10'),
            'motivo' => 'salud',
            'monto' => 10.0,
            'moneda' => 'USD',
        ]);

        $septiembreConAnticipo = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-09')
            ->firstOrFail();

        $this->assertEqualsWithDelta(10.0, (float) $septiembreConAnticipo->anticipos, 0.01);
        $this->assertEqualsWithDelta(
            $interesEsperado,
            (float) $septiembreConAnticipo->interes_mes,
            0.01
        );
    }

    public function test_la_hoja_de_calculo_incluye_el_tiempo_de_servicio(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2023-05-11');

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.hoja-calculo', $empleado->id))
            ->assertOk();

        $ruta = tempnam(sys_get_temp_dir(), 'prestaciones').'.ods';
        file_put_contents($ruta, $respuesta->streamedContent());

        $libro = IOFactory::load($ruta);
        $hoja = $libro->getSheetByName('Estado de cuenta');
        @unlink($ruta);

        $this->assertSame('Periodo', $hoja->getCell('B8')->getValue());
        $this->assertSame('Tiempo de servicio', $hoja->getCell('D8')->getValue());
        $this->assertSame('Años', $hoja->getCell('D9')->getValue());
        $this->assertSame('Mes(es)', $hoja->getCell('E9')->getValue());
        $this->assertSame('Día(s)', $hoja->getCell('F9')->getValue());
        $this->assertSame('11/05/2023', $hoja->getCell('B10')->getValue());
        $this->assertSame('10/06/2023', $hoja->getCell('C10')->getValue());
        $this->assertSame(0, (int) $hoja->getCell('D10')->getValue());
        $this->assertSame(1, (int) $hoja->getCell('E10')->getValue());
        $this->assertSame(0, (int) $hoja->getCell('F10')->getValue());
        $this->assertSame(1, (int) $hoja->getCell('A10')->getValue());
        $this->assertSame(2, (int) $hoja->getCell('A11')->getValue());

        $this->assertSame('Salario integral', $hoja->getCell('G8')->getValue());
        $this->assertSame('Saldo inicial período', $hoja->getCell('H8')->getValue());
        $this->assertSame('Depósito trimestral', $hoja->getCell('I8')->getValue());
        $this->assertSame('Anticipos', $hoja->getCell('M8')->getValue());
        $this->assertSame('Capital', $hoja->getCell('N8')->getValue());
        $this->assertSame('Tasa interés %', $hoja->getCell('O8')->getValue());
        $this->assertSame('Interés del mes', $hoja->getCell('P8')->getValue());
        $this->assertSame('Interés pagado', $hoja->getCell('R8')->getValue());
        $this->assertSame('Interés pendiente', $hoja->getCell('S8')->getValue());
        $this->assertSame('Saldo final período', $hoja->getCell('T8')->getValue());
    }

    public function test_el_pdf_sin_filtro_incluye_las_proyecciones_como_la_pantalla(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        $datosPdf = null;

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $vista, array $datos) use (&$datosPdf): bool {
                $datosPdf = $datos;

                return $vista === 'prestaciones.pdf';
            })
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('stream')->andReturn(response('pdf'));

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.pdf', $empleado->id))
            ->assertOk();

        $this->assertSame('2026-12', $datosPdf['hasta']);
        $this->assertTrue($datosPdf['incluyeProyeccion']);
        $this->assertTrue(
            $datosPdf['cuenta']->contains(fn ($fila) => (bool) $fila->es_proyeccion)
        );
    }

    public function test_el_pdf_con_hasta_explicito_no_agrega_proyecciones_posteriores(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);
        $prestaciones->proyectarHasta($empleado, '2026-12');

        $datosPdf = null;

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $vista, array $datos) use (&$datosPdf): bool {
                $datosPdf = $datos;

                return $vista === 'prestaciones.pdf';
            })
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('stream')->andReturn(response('pdf'));

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.pdf', [
                'empleadoId' => $empleado->id,
                'hasta' => '2026-10',
            ]))
            ->assertOk();

        $this->assertSame('2026-10', $datosPdf['hasta']);
        $this->assertFalse($datosPdf['incluyeProyeccion']);
        $this->assertFalse(
            $datosPdf['cuenta']->contains(fn ($fila) => (bool) $fila->es_proyeccion)
        );
    }

    public function test_el_pdf_incluye_las_columnas_de_intereses_en_orden(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $datosPdf = null;

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $vista, array $datos) use (&$datosPdf): bool {
                $datosPdf = $datos;

                return $vista === 'prestaciones.pdf';
            })
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->andReturnSelf();
        Pdf::shouldReceive('stream')->andReturn(response('pdf'));

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.pdf', $empleado->id))
            ->assertOk();

        $this->assertNotNull($datosPdf);

        $html = view('prestaciones.pdf', $datosPdf)->render();

        $posicionAnterior = -1;
        $columnas = [
            'Anticipos',
            'Capital período',
            'Tasa %',
            'Interés',
            'Int. pag.',
            'Int. pend. período',
            'Saldo final período',
        ];

        foreach ($columnas as $columna) {
            $posicion = mb_strpos($html, $columna);

            $this->assertNotFalse($posicion, "El PDF no incluye la columna {$columna}.");
            $this->assertGreaterThan($posicionAnterior, $posicion, "La columna {$columna} está fuera de orden.");

            $posicionAnterior = $posicion;
        }
    }

    public function test_el_neto_y_el_saldo_final_ignoran_el_exceso_de_un_pago_de_intereses(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);
        $movimientos = app(MovimientosPrestacionesService::class);

        $prestaciones->regenerar($empleado);

        // Un anticipo por todo el capital disponible deja la garantía en cero.
        $maximo = $movimientos->maximoAnticipo($empleado, Carbon::parse('2026-10-10'))['maximo'];
        $this->assertGreaterThan(0, $maximo);

        $movimientos->crear($empleado, [
            'tipo' => MovimientoPrestaciones::TIPO_ANTICIPO,
            'fecha' => Carbon::parse('2026-10-10'),
            'motivo' => 'salud',
            'monto' => $maximo,
            'moneda' => 'USD',
        ]);

        $septiembre = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-09')
            ->firstOrFail();

        $this->assertEqualsWithDelta(0.0, (float) $septiembre->capital_final, 0.01);

        // El pago cubre el interés pendiente y deja un exceso que no puede
        // aplicarse al capital porque ya está en cero.
        PagoInteresPrestaciones::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha' => '2026-10-10',
            'monto' => round((float) $septiembre->interes_pendiente_final + 5, 2),
            'moneda' => 'USD',
        ]);

        $prestaciones->regenerar($empleado);

        $septiembreFinal = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2026-09')
            ->firstOrFail();

        $this->assertGreaterThan(
            round((float) $septiembreFinal->interes_pendiente_inicial + (float) $septiembreFinal->interes_mes, 2),
            (float) $septiembreFinal->interes_pagado,
            'El pago debe exceder el interés pendiente del período.'
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.show', [
                'empleadoId' => $empleado->id,
                'desde' => '2026-01',
                'hasta' => '2026-10',
                'incluir_anteriores' => 1,
            ]))
            ->assertOk();

        $datos = $respuesta->viewData();
        $ultima = $datos['cuenta']->last();

        // El resumen debe mostrar el saldo real del cierre, igual que la tabla.
        $this->assertEqualsWithDelta(
            (float) $ultima->saldo_final_periodo,
            (float) $datos['saldoFinalRango'],
            0.01
        );

        // El neto es la variación real del saldo, no la suma de movimientos:
        // el pago completo incluye un exceso que no se aplicó al capital.
        $this->assertEqualsWithDelta(
            round((float) $datos['saldoFinalRango'] - (float) $datos['saldoInicialRango'], 2),
            (float) $datos['netoPeriodo'],
            0.01
        );

        $flujoBruto = round(
            (float) $datos['totalDepositos']
            + (float) $datos['totalAdicionales']
            + (float) $datos['totalIntereses']
            - (float) $datos['totalAnticipos']
            - (float) $datos['totalInteresesPagados'],
            2
        );

        $this->assertNotEqualsWithDelta($flujoBruto, (float) $datos['netoPeriodo'], 0.01);
    }

    public function test_una_liquidacion_por_aprobar_no_detiene_los_periodos(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado();

        $prestaciones = app(PrestacionesSocialesService::class);

        Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => '2025-06-30',
            'fecha_ingreso' => '2024-01-15',
            'fecha_egreso' => '2025-06-30',
            'motivo' => 'retiro_voluntario',
            'anios_servicio' => 1,
            'meses_fraccion' => 5,
            'dias_prestaciones' => 35,
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
            'total_asignaciones' => 0,
            'total_deducciones' => 0,
            'neto' => 0,
            'estatus' => 'por_aprobar',
        ]);

        $this->assertNull($prestaciones->fechaEgresoVigente($empleado));

        $requeridos = $prestaciones->periodosRequeridos($empleado, Carbon::parse('2026-12-31'));
        $this->assertSame('2026-12', end($requeridos));
    }

    public function test_el_listado_filtra_por_busqueda_y_estatus(): void
    {
        [$patrono] = $this->crearEmpleado();

        Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Luis',
            'apellidos' => 'Gómez',
            'cedula' => 'V-87654321',
            'fecha_ingreso' => '2023-05-10',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 400,
            'moneda' => 'USD',
            'estatus' => 'Inactivo',
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.index', ['buscar' => 'Gómez', 'estatus' => 'Inactivo']))
            ->assertOk()
            ->assertSee('V-87654321')
            ->assertSee('Inactivo')
            ->assertDontSee('V-12345678');
    }

    public function test_el_deposito_trimestral_se_registra_al_cierre_de_cada_trimestre(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2025-01-01');

        $prestaciones = app(PrestacionesSocialesService::class);

        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-01'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-02'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-03'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-04'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-05'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-06'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-09'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-12'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2026-03'));
    }

    public function test_un_ingreso_a_mitad_de_mes_cierra_el_trimestre_el_mismo_dia_del_mes_siguiente(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2025-01-10');

        $prestaciones = app(PrestacionesSocialesService::class);

        // La clave es el mes en que inicia el período: 10/03–09/04 cierra el
        // primer trimestre el 09/04, por eso el depósito aparece en 2025-03.
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-01'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-02'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-03'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-04'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-05'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-06'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-07'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-08'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-09'));
    }

    public function test_un_ingreso_el_dia_31_no_desborda_el_cierre_al_mes_siguiente(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2025-01-31');

        $prestaciones = app(PrestacionesSocialesService::class);

        // Trimestre 1: 31/01 + 3 meses sín desborde = 30/04, cierra el 29/04.
        // Ese cierre queda dentro del período 31/03–29/04 (clave 2025-03).
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-02'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2025-03'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-04'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2025-05'));
    }

    public function test_un_ingreso_el_29_de_febrero_cierra_trimestres_sin_desbordar(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2024-02-29');

        $prestaciones = app(PrestacionesSocialesService::class);

        // 29/02 + 3 meses sín desborde = 29/05, cierra el 28/05. El período
        // 29/04–28/05 tiene clave 2024-04.
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2024-03'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2024-04'));
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2024-05'));
    }

    public function test_los_dias_adicionales_se_abonan_en_el_periodo_que_completa_el_anio(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2020-01-15');

        $prestaciones = app(PrestacionesSocialesService::class);

        // El primer año se completa el 14/01/2021, pero el abono empieza a
        // partir del segundo año.
        $this->assertSame(0, $this->diasAdicionales($prestaciones, $empleado, '2020-12'));

        // El segundo año se completa el 14/01/2022: 2 días en ese cierre.
        $this->assertSame(2, $this->diasAdicionales($prestaciones, $empleado, '2021-12'));
        // El período siguiente al aniversario ya no abona días adicionales.
        $this->assertSame(0, $this->diasAdicionales($prestaciones, $empleado, '2022-01'));

        $this->assertSame(4, $this->diasAdicionales($prestaciones, $empleado, '2022-12'));
        $this->assertSame(6, $this->diasAdicionales($prestaciones, $empleado, '2023-12'));
        $this->assertSame(8, $this->diasAdicionales($prestaciones, $empleado, '2024-12'));
        $this->assertSame(10, $this->diasAdicionales($prestaciones, $empleado, '2025-12'));

        // El abono del último aniversario cumplido (año 6) es 10 días.
        $this->assertSame(10, $prestaciones->diasAdicionalesAcumulados($empleado, Carbon::parse('2026-10-20')));
    }

    public function test_el_egreso_el_dia_del_aniversario_abona_los_dias_adicionales(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2020-01-15');

        $calculo = app(PrestacionesSocialesService::class)->calcularMes(
            $empleado,
            Carbon::parse('2022-01'),
            false,
            Carbon::parse('2022-01-15')
        );

        $this->assertSame(2, $calculo['dias_adicionales_cont']);
    }

    public function test_los_dias_adicionales_no_superan_el_tope_de_30_por_abono(): void
    {
        $prestaciones = app(PrestacionesSocialesService::class);

        $this->assertSame(28, $prestaciones->diasAdicionalesDelAniversario(15));
        $this->assertSame(30, $prestaciones->diasAdicionalesDelAniversario(16));
        $this->assertSame(30, $prestaciones->diasAdicionalesDelAniversario(17));
        $this->assertSame(30, $prestaciones->diasAdicionalesDelAniversario(20));
    }

    public function test_el_egreso_a_mitad_de_trimestre_paga_la_fraccion_de_15_dias(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2024-01-15');

        $prestaciones = app(PrestacionesSocialesService::class);
        $egreso = Carbon::parse('2025-06-30');

        $calculo = $prestaciones->calcularMes($empleado, $egreso, false, $egreso);

        $this->assertSame(15, $calculo['dias_deposito']);
        $this->assertTrue($calculo['cierre']->isSameDay($egreso));
    }

    public function test_el_egreso_justo_en_el_cierre_del_trimestre_no_duplica_la_fraccion(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2024-01-15');

        $prestaciones = app(PrestacionesSocialesService::class);

        // El trimestre 5 cierra el 14/04/2025, dentro del período 15/03–14/04
        // (clave 2025-03): se abonan los 15 días del cierre.
        $enCierre = $prestaciones->calcularMes(
            $empleado,
            Carbon::createFromFormat('Y-m', '2025-03'),
            false,
            Carbon::parse('2025-04-14')
        );
        $this->assertSame(15, $enCierre['dias_deposito']);

        // Al día siguiente ya empieza otro período: solo la fracción de 15
        // días, sin repetir el cierre que quedó en el período anterior.
        $diaSiguiente = $prestaciones->calcularMes(
            $empleado,
            Carbon::createFromFormat('Y-m', '2025-04'),
            false,
            Carbon::parse('2025-04-15')
        );
        $this->assertSame(15, $diaSiguiente['dias_deposito']);
    }

    public function test_el_periodo_de_egreso_guarda_el_corte_y_la_fraccion(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2024-01-15');

        Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => '2025-06-30',
            'fecha_ingreso' => '2024-01-15',
            'fecha_egreso' => '2025-06-30',
            'motivo' => 'retiro_voluntario',
            'anios_servicio' => 1,
            'meses_fraccion' => 5,
            'dias_prestaciones' => 35,
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
            'total_asignaciones' => 0,
            'total_deducciones' => 0,
            'neto' => 0,
            'estatus' => 'aprobada',
        ]);

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);

        $registro = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2025-06')
            ->firstOrFail();

        $this->assertSame('2025-06-30', $registro->fecha_cierre->toDateString());
        $this->assertSame(15, (int) $registro->dias_deposito);
        $this->assertSame('2025-06', PrestacionSocial::where('empleado_id', $empleado->id)->max('periodo'));

        $egreso = Carbon::parse('2025-06-30');
        $this->assertEqualsWithDelta(
            $prestaciones->garantiaBrutaEn($empleado, $egreso),
            $prestaciones->garantiaBrutaAlEgreso($empleado, $egreso),
            0.01
        );
    }

    public function test_aprobar_la_liquidacion_recalcula_los_periodos_con_corte_en_el_egreso(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2024-01-15');

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);

        // Antes de aprobar, el período 15/06–14/07 cierra en el trimestre y
        // abona los 15 días completos.
        $antes = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2025-06')
            ->firstOrFail();
        $this->assertSame('2025-07-14', $antes->fecha_cierre->toDateString());
        $this->assertSame(15, (int) $antes->dias_deposito);

        $liquidacion = Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => '2025-06-30',
            'fecha_ingreso' => '2024-01-15',
            'fecha_egreso' => '2025-06-30',
            'motivo' => 'retiro_voluntario',
            'anios_servicio' => 1,
            'meses_fraccion' => 5,
            'dias_prestaciones' => 35,
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
            'total_asignaciones' => 0,
            'total_deducciones' => 0,
            'neto' => 0,
            'estatus' => 'por_aprobar',
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('liquidaciones.aprobar', $liquidacion->id))
            ->assertRedirect(route('liquidaciones.show', $liquidacion->id));

        $registro = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('periodo', '2025-06')
            ->firstOrFail();

        $this->assertSame('2025-06-30', $registro->fecha_cierre->toDateString());
        $this->assertSame(15, (int) $registro->dias_deposito);
        $this->assertSame(
            '2025-06',
            PrestacionSocial::where('empleado_id', $empleado->id)->max('periodo')
        );
    }

    public function test_un_ingreso_el_dia_1_cierra_cada_periodo_al_fin_de_mes(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2020-01-01');

        $prestaciones = app(PrestacionesSocialesService::class);

        $this->assertSame(
            '2020-01-01',
            $prestaciones->inicioPeriodo($empleado, Carbon::parse('2020-01'))->toDateString()
        );
        $this->assertSame(
            '2020-01-31',
            $prestaciones->finPeriodo($empleado, Carbon::parse('2020-01'))->toDateString()
        );
        $this->assertSame(
            '2020-02-01',
            $prestaciones->inicioPeriodo($empleado, Carbon::parse('2020-02'))->toDateString()
        );
        $this->assertSame(
            '2020-02-29',
            $prestaciones->finPeriodo($empleado, Carbon::parse('2020-02'))->toDateString()
        );
    }

    public function test_un_ingreso_el_dia_11_cierra_cada_periodo_el_dia_10_del_mes_siguiente(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2023-05-11');

        $prestaciones = app(PrestacionesSocialesService::class);

        $this->assertSame(
            '2023-05-11',
            $prestaciones->inicioPeriodo($empleado, Carbon::parse('2023-05'))->toDateString()
        );
        $this->assertSame(
            '2023-06-10',
            $prestaciones->finPeriodo($empleado, Carbon::parse('2023-05'))->toDateString()
        );
        $this->assertSame(
            '2023-06-11',
            $prestaciones->inicioPeriodo($empleado, Carbon::parse('2023-06'))->toDateString()
        );
        $this->assertSame(
            '2023-07-10',
            $prestaciones->finPeriodo($empleado, Carbon::parse('2023-06'))->toDateString()
        );

        // Una fecha dentro del período se resuelve a su mes de inicio.
        $this->assertSame('2023-05', $prestaciones->periodoDeFecha($empleado, Carbon::parse('2023-06-05')));
        $this->assertSame('2023-06', $prestaciones->periodoDeFecha($empleado, Carbon::parse('2023-06-11')));

        // El primer trimestre cierra el 10/08, dentro del período 11/07–10/08.
        $this->assertSame(0, $this->diasDeposito($prestaciones, $empleado, '2023-06'));
        $this->assertSame(15, $this->diasDeposito($prestaciones, $empleado, '2023-07'));
    }

    public function test_un_egreso_de_mitad_de_mes_paga_la_fraccion_en_el_periodo_correcto(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2023-05-11');

        $prestaciones = app(PrestacionesSocialesService::class);
        $egreso = Carbon::parse('2023-07-20');

        $calculo = $prestaciones->calcularMes(
            $empleado,
            Carbon::createFromFormat('Y-m', '2023-07'),
            false,
            $egreso
        );

        $this->assertSame(15, $calculo['dias_deposito']);
        $this->assertTrue($calculo['cierre']->isSameDay($egreso));
        $this->assertSame('2023-07', $prestaciones->periodoDeFecha($empleado, $egreso));
    }

    public function test_la_tabla_muestra_el_rango_de_fechas_y_el_tiempo_de_servicio(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2023-05-11');

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.show', $empleado->id))
            ->assertOk()
            ->assertSee('Tiempo de servicio')
            ->assertSee('0 a · 1 m · 0 d')
            ->assertDontSee('Mes(es)')
            ->assertDontSee('Día(s)')
            ->assertSee('11/05/2023')
            ->assertSee('10/06/2023')
            ->assertSee('11/06/2023')
            ->assertSee('10/07/2023');
    }

    public function test_las_columnas_de_la_tabla_siguen_el_orden_del_estado_de_cuenta(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2023-05-11');

        app(PrestacionesSocialesService::class)->regenerar($empleado);

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('prestaciones.show', $empleado->id))
            ->assertOk()
            ->assertSeeInOrder([
                'Sal. integral',
                'Saldo inicial período',
                'Depósito trim.',
                'Días adic.',
                'Anticipos',
                'Capital período',
                'Tasa interés %',
                'Interés',
                'Int. pag.',
                'Int. pend. período',
                'Saldo final período',
            ]);
    }

    public function test_el_tiempo_de_servicio_acumula_meses_desde_el_ingreso(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2023-05-11');

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);

        $cuenta = $prestaciones->estadoDeCuenta($empleado)->keyBy('periodo');

        $this->assertSame(
            ['anios' => 0, 'meses' => 1, 'dias' => 0],
            $this->servicio($cuenta['2023-05'])
        );
        $this->assertSame(
            ['anios' => 1, 'meses' => 0, 'dias' => 0],
            $this->servicio($cuenta['2024-04'])
        );
        $this->assertSame(
            ['anios' => 1, 'meses' => 4, 'dias' => 0],
            $this->servicio($cuenta['2024-08'])
        );
    }

    public function test_el_tiempo_de_servicio_suma_dias_en_el_periodo_de_egreso(): void
    {
        [$patrono, $empleado] = $this->crearEmpleado('2023-05-11');

        Liquidacion::create([
            'patrono_id' => $patrono->id,
            'empleado_id' => $empleado->id,
            'fecha_liquidacion' => '2023-07-20',
            'fecha_ingreso' => '2023-05-11',
            'fecha_egreso' => '2023-07-20',
            'motivo' => 'retiro_voluntario',
            'anios_servicio' => 0,
            'meses_fraccion' => 2,
            'dias_prestaciones' => 5,
            'salario_integral_diario' => 10,
            'moneda' => 'USD',
            'total_asignaciones' => 0,
            'total_deducciones' => 0,
            'neto' => 0,
            'estatus' => 'aprobada',
        ]);

        $prestaciones = app(PrestacionesSocialesService::class);
        $prestaciones->regenerar($empleado);

        $registro = $prestaciones->estadoDeCuenta($empleado)->firstWhere('periodo', '2023-07');

        $this->assertNotNull($registro);
        $this->assertSame(
            ['anios' => 0, 'meses' => 2, 'dias' => 10],
            $this->servicio($registro)
        );
    }

    /**
     * @return array{anios:int, meses:int, dias:int}
     */
    private function servicio(object $registro): array
    {
        return [
            'anios' => (int) $registro->servicio_anios,
            'meses' => (int) $registro->servicio_meses,
            'dias' => (int) $registro->servicio_dias,
        ];
    }

    private function diasDeposito(
        PrestacionesSocialesService $prestaciones,
        Empleado $empleado,
        string $periodo
    ): int {
        return $prestaciones->calcularMes(
            $empleado,
            Carbon::createFromFormat('Y-m', $periodo)->startOfMonth()
        )['dias_deposito'];
    }

    private function diasAdicionales(
        PrestacionesSocialesService $prestaciones,
        Empleado $empleado,
        string $periodo
    ): int {
        return $prestaciones->calcularMes(
            $empleado,
            Carbon::createFromFormat('Y-m', $periodo)->startOfMonth()
        )['dias_adicionales_cont'];
    }

    /**
     * @return array{0:Patrono, 1:Empleado}
     */
    private function crearEmpleado(string $fechaIngreso = '2024-01-15'): array
    {
        $patrono = Patrono::create([
            'rif' => 'J-12345678-9',
            'razon_social' => 'Patrono de pruebas',
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
            'cedula' => 'V-12345678',
            'fecha_ingreso' => $fechaIngreso,
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_inicial' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);

        return [$patrono, $empleado];
    }
}
