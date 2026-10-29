<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\RolUsuario;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\User;

trait CreaEscenarioSeguridad
{
    protected function crearPatrono(string $rif, string $razonSocial): Patrono
    {
        return Patrono::create([
            'rif' => $rif,
            'razon_social' => $razonSocial,
            'estado' => 'Activo',
            'dias_bonificacion' => 30,
            'deposito_tipo' => 'contabilidad',
            'anticipos_max_anio' => 2,
            'anticipos_pct_contabilidad' => 100,
        ]);
    }

    protected function crearEmpleado(Patrono $patrono, string $cedula = 'V-12345678'): Empleado
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
            'salario_base' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ]);
    }

    /**
     * @param  array<int, Patrono>  $patronos
     */
    protected function crearUsuario(RolUsuario $rol, array $patronos = []): User
    {
        $usuario = User::factory()->create(['rol' => $rol]);
        $usuario->patronos()->sync(array_map(fn (Patrono $patrono) => $patrono->id, $patronos));

        return $usuario;
    }

    /**
     * @return array<string, mixed>
     */
    protected function datosEmpleado(string $cedula = 'V-87654321'): array
    {
        return [
            'nombres' => 'Luis',
            'apellidos' => 'Gómez',
            'cedula' => $cedula,
            'fecha_ingreso' => '2025-02-01',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_base' => '450,00',
            'moneda' => 'USD',
            'estatus' => 'Activo',
        ];
    }
}
