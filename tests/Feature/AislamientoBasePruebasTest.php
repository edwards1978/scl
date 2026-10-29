<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AislamientoBasePruebasTest extends TestCase
{
    public function test_la_suite_usa_la_base_de_pruebas_y_no_la_de_produccion(): void
    {
        $conexion = DB::connection();

        $this->assertSame('scl_test', $conexion->getDatabaseName());
        $this->assertSame('', $conexion->getTablePrefix());
    }
}
