<?php

namespace Tests\Unit;

use App\Services\DiasFeriadosService;
use PHPUnit\Framework\TestCase;

class DiasFeriadosServiceTest extends TestCase
{
    public function test_rechaza_anios_fuera_del_rango_admitido(): void
    {
        $service = new DiasFeriadosService;

        $this->assertSame(0, $service->materializarAnio(8202));
        $this->assertSame([], $service->asegurarAnio(8202));

        $this->assertSame(0, $service->materializarAnio(1));
        $this->assertSame([], $service->asegurarAnio(1));

        $this->assertSame(0, $service->materializarAnio(2101));
        $this->assertSame([], $service->asegurarAnio(2101));
    }

    public function test_anio_valido_dentro_del_rango(): void
    {
        $service = new DiasFeriadosService;

        $this->assertTrue($service->anioValido(2000));
        $this->assertTrue($service->anioValido(2026));
        $this->assertTrue($service->anioValido(2100));
        $this->assertFalse($service->anioValido(1999));
        $this->assertFalse($service->anioValido(8202));
    }
}
