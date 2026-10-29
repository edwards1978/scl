<?php

namespace Tests\Feature\Calculos;

use App\Models\CalculoLiquidacionLiteralC;
use App\Models\User;
use App\Services\CalculoLiquidacionLiteralCService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CalculoLiquidacionLiteralCTest extends TestCase
{
    public function test_calcula_literal_c_vacaciones_utilidades_y_vencidas(): void
    {
        $resultado = $this->calcular([
            'fecha_ingreso' => '2021-01-15',
            'fecha_egreso' => '2026-07-15',
            'salario' => 30000,
            'anios_vacaciones_vencidas' => 5,
            'anios_utilidades_vencidas' => 5,
        ]);

        $this->assertSame(5, $resultado['anios_servicio']);
        $this->assertSame(180, $resultado['dias_prestaciones']);
        $this->assertEqualsWithDelta(1138.89, $resultado['salario_diario_integral'], 0.01);

        $conceptos = collect($resultado['asignaciones']);

        $this->assertEqualsWithDelta(10, $conceptos->firstWhere('concepto', 'Vacaciones fraccionadas')['dias'], 0.01);
        $this->assertEqualsWithDelta(15, $conceptos->firstWhere('concepto', 'Utilidades fraccionadas')['dias'], 0.01);
        $this->assertEqualsWithDelta(85, $conceptos->firstWhere('concepto', 'Vacaciones vencidas')['dias'], 0.01);
        $this->assertEqualsWithDelta(85, $conceptos->firstWhere('concepto', 'Bono vacacional vencido')['dias'], 0.01);
        $this->assertEqualsWithDelta(150, $conceptos->firstWhere('concepto', 'Utilidades vencidas')['dias'], 0.01);
    }

    public function test_convierte_el_salario_segun_el_tipo(): void
    {
        $servicio = app(CalculoLiquidacionLiteralCService::class);

        $this->assertEqualsWithDelta(1000, $servicio->salarioNormalDiario(30000, CalculoLiquidacionLiteralC::TIPO_MENSUAL), 0.01);
        $this->assertEqualsWithDelta(100, $servicio->salarioNormalDiario(700, CalculoLiquidacionLiteralC::TIPO_SEMANAL), 0.01);
        $this->assertEqualsWithDelta(100, $servicio->salarioNormalDiario(1500, CalculoLiquidacionLiteralC::TIPO_QUINCENAL), 0.01);
        $this->assertEqualsWithDelta(100, $servicio->salarioNormalDiario(100, CalculoLiquidacionLiteralC::TIPO_DIARIO), 0.01);
    }

    public function test_un_anio_calendario_completo_cuenta_doce_meses(): void
    {
        $resultado = $this->calcular([
            'fecha_ingreso' => '2026-01-01',
            'fecha_egreso' => '2026-12-31',
        ]);

        $conceptos = collect($resultado['asignaciones']);

        $this->assertSame(12, $resultado['meses_vacaciones_fraccionadas']);
        $this->assertSame(12, $resultado['meses_utilidades_fraccionadas']);
        $this->assertEqualsWithDelta(15, $conceptos->firstWhere('concepto', 'Vacaciones fraccionadas')['dias'], 0.01);
        $this->assertEqualsWithDelta(15, $conceptos->firstWhere('concepto', 'Bono vacacional fraccionado')['dias'], 0.01);
        $this->assertEqualsWithDelta(30, $conceptos->firstWhere('concepto', 'Utilidades fraccionadas')['dias'], 0.01);
    }

    public function test_quince_dias_completan_el_mes_solo_para_literal_c(): void
    {
        $catorce = $this->calcular(['fecha_ingreso' => '2025-01-15', 'fecha_egreso' => '2026-06-29']);
        $quince = $this->calcular(['fecha_ingreso' => '2025-01-15', 'fecha_egreso' => '2026-06-30']);

        $this->assertSame(30, $catorce['dias_prestaciones']);
        $this->assertSame(60, $quince['dias_prestaciones']);
    }

    public function test_limita_los_anios_vencidos_a_los_anios_de_servicio(): void
    {
        $this->expectException(ValidationException::class);

        $this->calcular([
            'fecha_ingreso' => '2024-01-15',
            'fecha_egreso' => '2026-07-15',
            'anios_vacaciones_vencidas' => 3,
        ]);
    }

    public function test_guarda_edita_reimprime_y_elimina_sin_patrono_contexto(): void
    {
        $usuario = User::factory()->administrador()->create();
        $datos = $this->datosFormulario();

        $this->actingAs($usuario)
            ->post(route('calculos-literal-c.store'), $datos)
            ->assertRedirect();

        $calculo = CalculoLiquidacionLiteralC::firstOrFail();
        $this->assertSame($usuario->id, $calculo->created_by);

        $this->actingAs($usuario)
            ->get(route('calculos-literal-c.create'))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('calculos-literal-c.edit', $calculo))
            ->assertOk();

        $this->actingAs($usuario)
            ->get(route('calculos-literal-c.show', $calculo))
            ->assertOk()
            ->assertSee('Liquidación rápida Art. 142.c');

        $this->actingAs($usuario)
            ->get(route('calculos-literal-c.pdf', $calculo))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $netoAnterior = (float) $calculo->neto;

        $this->actingAs($usuario)
            ->put(route('calculos-literal-c.update', $calculo), array_merge($datos, ['salario' => 45000]))
            ->assertRedirect(route('calculos-literal-c.show', $calculo));

        $this->assertSame($usuario->id, $calculo->fresh()->updated_by);
        $this->assertNotEquals($netoAnterior, (float) $calculo->fresh()->neto);

        $this->actingAs($usuario)
            ->delete(route('calculos-literal-c.destroy', $calculo))
            ->assertRedirect(route('calculos-literal-c.index'));

        $this->assertDatabaseMissing('calculos_liquidacion_literal_c', ['id' => $calculo->id]);
    }

    public function test_consulta_puede_leer_pero_no_gestionar(): void
    {
        $consulta = User::factory()->consulta()->create();
        $calculo = CalculoLiquidacionLiteralC::create($this->atributosCalculo());

        $this->actingAs($consulta)
            ->get(route('calculos-literal-c.index'))
            ->assertOk();

        $this->actingAs($consulta)
            ->get(route('calculos-literal-c.show', $calculo))
            ->assertOk();

        $this->actingAs($consulta)
            ->get(route('calculos-literal-c.create'))
            ->assertForbidden();

        $this->actingAs($consulta)
            ->post(route('calculos-literal-c.store'), $this->datosFormulario())
            ->assertForbidden();
    }

    public function test_la_tabla_es_independiente_de_patronos_y_empleados(): void
    {
        $this->assertTrue(Schema::hasTable('calculos_liquidacion_literal_c'));
        $this->assertFalse(Schema::hasColumn('calculos_liquidacion_literal_c', 'patrono_id'));
        $this->assertFalse(Schema::hasColumn('calculos_liquidacion_literal_c', 'empleado_id'));
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function calcular(array $cambios = []): array
    {
        return app(CalculoLiquidacionLiteralCService::class)->calcular(array_merge([
            'fecha_ingreso' => '2021-01-15',
            'fecha_egreso' => '2026-07-15',
            'tipo_salario' => CalculoLiquidacionLiteralC::TIPO_MENSUAL,
            'salario' => 30000,
            'moneda' => CalculoLiquidacionLiteralC::MONEDA_USD,
            'dias_utilidades' => 30,
            'anios_vacaciones_vencidas' => 0,
            'anios_utilidades_vencidas' => 0,
            'anticipo' => 0,
            'intereses_pagados' => 0,
            'prestamo_saldo' => 0,
        ], $cambios));
    }

    /** @return array<string, mixed> */
    private function datosFormulario(): array
    {
        return [
            'patrono_nombre' => 'Patrono independiente',
            'patrono_rif' => 'J-12345678-9',
            'trabajador_nombre' => 'Trabajador independiente',
            'trabajador_cedula' => 'V-12345678',
            'cargo' => 'Analista',
            'fecha_ingreso' => '2021-01-15',
            'fecha_egreso' => '2026-07-15',
            'tipo_salario' => CalculoLiquidacionLiteralC::TIPO_MENSUAL,
            'salario' => 30000,
            'moneda' => CalculoLiquidacionLiteralC::MONEDA_USD,
            'dias_utilidades' => 30,
            'anios_vacaciones_vencidas' => 0,
            'anios_utilidades_vencidas' => 0,
            'anticipo' => 100,
            'intereses_pagados' => 50,
            'prestamo_saldo' => 200,
        ];
    }

    /** @return array<string, mixed> */
    private function atributosCalculo(): array
    {
        $datos = app(CalculoLiquidacionLiteralCService::class)->calcular($this->datosFormulario());

        return array_merge($this->datosFormulario(), [
            'salario_diario_normal' => $datos['salario_diario_normal'],
            'salario_diario_integral' => $datos['salario_diario_integral'],
            'tasa_bcv' => $datos['tasa_bcv'],
            'total_asignaciones' => $datos['total_asignaciones'],
            'total_deducciones' => $datos['total_deducciones'],
            'neto' => $datos['neto'],
            'conceptos' => $datos['conceptos'],
        ]);
    }
}
