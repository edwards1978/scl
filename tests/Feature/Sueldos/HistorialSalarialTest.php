<?php

declare(strict_types=1);

namespace Tests\Feature\Sueldos;

use App\Enums\RolUsuario;
use App\Livewire\Entidades\CrearEmpleado;
use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\Sueldo;
use Livewire\Livewire;
use Tests\Feature\Seguridad\CreaEscenarioSeguridad;
use Tests\TestCase;

class HistorialSalarialTest extends TestCase
{
    use CreaEscenarioSeguridad;

    public function test_crear_empleado_registra_salario_inicial_y_cambio_de_ingreso(): void
    {
        $patrono = $this->crearPatrono('J-50000001-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.store'), $this->datosEmpleado('V-50000001'))
            ->assertRedirect(route('empleados.index'));

        $empleado = Empleado::where('cedula', 'V-50000001')->firstOrFail();

        $this->assertEqualsWithDelta(450.0, (float) $empleado->salario_inicial, 0.01);

        $cambio = CambioSalario::where('empleado_id', $empleado->id)->sole();

        $this->assertSame('2025-02-01', $cambio->fecha_cambio->toDateString());
        $this->assertEqualsWithDelta(450.0, (float) $cambio->monto, 0.01);
        $this->assertSame('USD', $cambio->moneda);
    }

    public function test_crear_empleado_desde_livewire_registra_el_historial(): void
    {
        $patrono = $this->crearPatrono('J-50000002-1', 'Patrono A');
        $usuario = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);
        session(['patrono_id' => $patrono->id]);

        Livewire::actingAs($usuario)
            ->test(CrearEmpleado::class)
            ->set('nombres', 'Luis')
            ->set('apellidos', 'Gómez')
            ->set('cedula', 'V-50000002')
            ->set('fecha_ingreso', '2025-03-01')
            ->set('salario_base', '500')
            ->set('moneda', 'USD')
            ->call('guardar')
            ->assertHasNoErrors();

        $empleado = Empleado::where('cedula', 'V-50000002')->firstOrFail();

        $this->assertEqualsWithDelta(500.0, (float) $empleado->salario_inicial, 0.01);

        $cambio = CambioSalario::where('empleado_id', $empleado->id)->sole();

        $this->assertSame('2025-03-01', $cambio->fecha_cambio->toDateString());
        $this->assertEqualsWithDelta(500.0, (float) $cambio->monto, 0.01);
    }

    public function test_editar_el_salario_desde_la_ficha_registra_el_cambio_con_la_vigencia(): void
    {
        $patrono = $this->crearPatrono('J-50000003-1', 'Patrono A');
        $empleado = $this->crearEmpleado($patrono, 'V-50000003');
        $usuario = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $datos = $this->datosEmpleado('V-50000003');
        $datos['salario_base'] = '600';
        $datos['fecha_vigencia_salario'] = '2025-06-01';

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('empleados.update', $empleado), $datos)
            ->assertRedirect(route('empleados.index'));

        $empleado->refresh();

        $this->assertEqualsWithDelta(600.0, (float) $empleado->salario_base, 0.01);

        $cambio = CambioSalario::where('empleado_id', $empleado->id)->sole();

        $this->assertSame('2025-06-01', $cambio->fecha_cambio->toDateString());
        $this->assertEqualsWithDelta(600.0, (float) $cambio->monto, 0.01);
    }

    public function test_editar_la_ficha_sin_cambiar_el_salario_no_registra_cambios(): void
    {
        $patrono = $this->crearPatrono('J-50000004-1', 'Patrono A');
        $empleado = $this->crearEmpleado($patrono, 'V-50000004');
        $usuario = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $datos = $this->datosEmpleado('V-50000004');
        $datos['salario_base'] = '300';

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->put(route('empleados.update', $empleado), $datos)
            ->assertRedirect(route('empleados.index'));

        $this->assertSame(0, CambioSalario::where('empleado_id', $empleado->id)->count());
    }

    public function test_registrar_una_variacion_recalcula_los_sueldos_del_periodo(): void
    {
        $patrono = $this->crearPatrono('J-50000005-1', 'Patrono A');
        $empleado = $this->crearEmpleado($patrono, 'V-50000005');
        $usuario = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        Sueldo::create([
            'empleado_id' => $empleado->id,
            'periodo' => '2025-05',
            'fecha_cierre' => '2025-05-31',
            'moneda_sueldo' => 'USD',
            'monto_usd' => 300,
            'origen' => 'ficha',
        ]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('sueldos.variaciones.store'), [
                'empleado_id' => $empleado->id,
                'fecha_cambio' => '2025-06-15',
                'monto' => 500,
                'moneda' => 'USD',
            ])
            ->assertRedirect(route('sueldos.variaciones', ['empleado_id' => $empleado->id]));

        $cambio = CambioSalario::where('empleado_id', $empleado->id)->sole();

        $this->assertSame('2025-06-15', $cambio->fecha_cambio->toDateString());
        $this->assertEqualsWithDelta(500.0, (float) $cambio->monto, 0.01);

        $mayo = Sueldo::where('empleado_id', $empleado->id)->where('periodo', '2025-05')->sole();
        $junio = Sueldo::where('empleado_id', $empleado->id)->where('periodo', '2025-06')->sole();

        $this->assertEqualsWithDelta(300.0, (float) $mayo->monto_usd, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $junio->monto_usd, 0.01);
    }

    public function test_eliminar_una_variacion_restaura_el_salario_anterior_y_recalcula(): void
    {
        $patrono = $this->crearPatrono('J-50000006-1', 'Patrono A');
        $empleado = $this->crearEmpleado($patrono, 'V-50000006');
        $empleado->update(['salario_base' => 500]);
        $usuario = $this->crearUsuario(RolUsuario::Administrador, [$patrono]);

        $cambio = CambioSalario::create([
            'empleado_id' => $empleado->id,
            'fecha_cambio' => '2025-06-01',
            'monto' => 500,
            'moneda' => 'USD',
        ]);

        Sueldo::create([
            'empleado_id' => $empleado->id,
            'periodo' => '2025-06',
            'fecha_cierre' => '2025-06-30',
            'moneda_sueldo' => 'USD',
            'monto_usd' => 500,
            'origen' => 'cambio',
        ]);

        $this->actingAs($usuario)
            ->withSession(['patrono_id' => $patrono->id])
            ->delete(route('sueldos.variaciones.destroy', $cambio))
            ->assertRedirect(route('sueldos.variaciones', ['empleado_id' => $empleado->id]));

        $this->assertSame(0, CambioSalario::where('empleado_id', $empleado->id)->count());

        $empleado->refresh();
        $this->assertEqualsWithDelta(300.0, (float) $empleado->salario_base, 0.01);

        $junio = Sueldo::where('empleado_id', $empleado->id)->where('periodo', '2025-06')->sole();
        $this->assertEqualsWithDelta(300.0, (float) $junio->monto_usd, 0.01);
    }

    public function test_el_comando_completa_el_historial_de_trabajadores_antiguos(): void
    {
        $patrono = $this->crearPatrono('J-50000007-1', 'Patrono A');

        $empleado = Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Carlos',
            'apellidos' => 'Antiguo',
            'cedula' => 'V-50000007',
            'fecha_ingreso' => '2024-01-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_base' => 350,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);

        $this->artisan('salarios:completar-historial', ['--aplicar' => true])
            ->assertSuccessful();

        $empleado->refresh();

        $this->assertEqualsWithDelta(350.0, (float) $empleado->salario_inicial, 0.01);

        $cambio = CambioSalario::where('empleado_id', $empleado->id)->sole();

        $this->assertSame('2024-01-15', $cambio->fecha_cambio->toDateString());
        $this->assertEqualsWithDelta(350.0, (float) $cambio->monto, 0.01);
    }

    public function test_el_comando_sin_aplicar_no_modifica_datos(): void
    {
        $patrono = $this->crearPatrono('J-50000008-1', 'Patrono A');

        $empleado = Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Carlos',
            'apellidos' => 'Antiguo',
            'cedula' => 'V-50000008',
            'fecha_ingreso' => '2024-01-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_base' => 350,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);

        $this->artisan('salarios:completar-historial')->assertSuccessful();

        $empleado->refresh();

        $this->assertNull($empleado->salario_inicial);
        $this->assertSame(0, CambioSalario::where('empleado_id', $empleado->id)->count());
    }

    public function test_el_comando_es_idempotente(): void
    {
        $patrono = $this->crearPatrono('J-50000009-1', 'Patrono A');
        $empleado = $this->crearEmpleado($patrono, 'V-50000009');
        $empleado->update(['salario_inicial' => null]);

        $this->artisan('salarios:completar-historial', ['--aplicar' => true])->assertSuccessful();
        $this->artisan('salarios:completar-historial', ['--aplicar' => true])->assertSuccessful();

        $this->assertSame(1, CambioSalario::where('empleado_id', $empleado->id)->count());
    }
}
