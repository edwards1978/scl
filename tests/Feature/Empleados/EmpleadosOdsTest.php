<?php

declare(strict_types=1);

namespace Tests\Feature\Empleados;

use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use Tests\TestCase;

class EmpleadosOdsTest extends TestCase
{
    public function test_exporta_los_empleados_del_patrono_actual(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $otroPatrono = $this->crearPatrono('J-40404040-4', 'Otro patrono');
        Empleado::create([
            'patrono_id' => $otroPatrono->id,
            'nombres' => 'Carlos',
            'apellidos' => 'Ajeno',
            'cedula' => 'V-40404040',
            'fecha_ingreso' => '2024-02-01',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_base' => 999,
            'moneda' => 'BS',
            'estatus' => 'Activo',
        ]);

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('empleados.exportar'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.oasis.opendocument.spreadsheet');

        $libro = $this->cargarOds($respuesta->streamedContent());
        $hoja = $libro->getSheetByName('Empleados');
        $instrucciones = $libro->getSheetByName('Instrucciones');

        $this->assertNotNull($hoja);
        $this->assertSame('Cédula', $hoja->getCell('A1')->getValue());
        $this->assertSame('Capitalización Documento', $hoja->getCell('V1')->getValue());
        $this->assertSame(2, $hoja->getHighestDataRow());
        $this->assertSame('V-30303030', $hoja->getCell('A2')->getValue());
        $this->assertSame('Ana', $hoja->getCell('B2')->getValue());
        $this->assertSame('Pérez', $hoja->getCell('C2')->getValue());
        $this->assertSame('15/03/2024', $hoja->getCell('I2')->getValue());
        $this->assertEqualsWithDelta(300.0, (float) $hoja->getCell('O2')->getValue(), 0.01);
        $this->assertSame('USD', $hoja->getCell('Q2')->getValue());
        $this->assertSame('No', $hoja->getCell('T2')->getValue());

        $this->assertNotNull($instrucciones);
        $this->assertStringContainsString(
            'INSTRUCCIONES PARA ACTUALIZAR EMPLEADOS',
            (string) $instrucciones->getCell('A1')->getValue()
        );
        $this->assertSame('Empleados', $libro->getActiveSheet()->getTitle());
    }

    public function test_la_plantilla_incluye_encabezados_e_instrucciones(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->get(route('empleados.plantilla'))
            ->assertOk();

        $libro = $this->cargarOds($respuesta->streamedContent());
        $empleados = $libro->getSheetByName('Empleados');
        $instrucciones = $libro->getSheetByName('Instrucciones');

        $this->assertNotNull($empleados);
        $this->assertNotNull($instrucciones);
        $this->assertSame('Cédula', $empleados->getCell('A1')->getValue());
        $this->assertSame(1, $empleados->getHighestDataRow());
        $this->assertStringContainsString(
            'PLANTILLA PARA IMPORTAR EMPLEADOS',
            (string) $instrucciones->getCell('A1')->getValue()
        );
    }

    public function test_importa_empleados_nuevos_interpretando_fechas_y_numeros(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $archivo = $this->archivoOds(
            [
                'Cédula', 'Nombres', 'Apellidos', 'Fecha de Nacimiento', 'Fecha de Ingreso',
                'Tipo de Contrato', 'Tipo de Salario', 'Salario Base', 'Salario Inicial',
                'Moneda', 'Estatus', 'Días de Descanso', 'Capitaliza Intereses',
                'Capitalización Autorizada Desde', 'Capitalización Documento',
            ],
            [
                [
                    'V12345678', 'Luis', 'Gómez', '10/05/1990', '15/03/2024',
                    'Indeterminado', 'Fijo', '1.234,56', '', 'BS', 'Activo', '6,7',
                    'No', '', '',
                ],
            ]
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo]);

        $respuesta->assertRedirect(route('empleados.index'))
            ->assertSessionHas('success');

        $empleado = Empleado::where('patrono_id', $patrono->id)
            ->where('cedula', 'V-12345678')
            ->firstOrFail();

        $this->assertSame('Luis', $empleado->nombres);
        $this->assertSame('Gómez', $empleado->apellidos);
        $this->assertSame('1990-05-10', $empleado->fecha_nacimiento?->toDateString());
        $this->assertSame('2024-03-15', $empleado->fecha_ingreso->toDateString());
        $this->assertEqualsWithDelta(1234.56, (float) $empleado->salario_base, 0.01);
        $this->assertEqualsWithDelta(1234.56, (float) $empleado->salario_inicial, 0.01);
        $this->assertSame('6,7', $empleado->dias_descanso);
        $this->assertFalse($empleado->capitaliza_intereses);
    }

    public function test_actualiza_los_empleados_existentes_por_cedula(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $archivo = $this->archivoOds(
            [
                'Cédula', 'Nombres', 'Apellidos', 'Fecha de Ingreso', 'Tipo de Contrato',
                'Tipo de Salario', 'Salario Base', 'Moneda', 'Estatus', 'Cargo',
            ],
            [
                ['V-30303030', 'Ana Actualizada', 'Pérez', '01/02/2024', 'Indeterminado', 'Fijo', '500', 'USD', 'Suspendido', 'Gerente'],
            ]
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo]);

        $respuesta->assertRedirect(route('empleados.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('importacion_detalle', function (array $detalle): bool {
                return $detalle['actualizados'] === ['V-30303030']
                    && $detalle['actualizados_total'] === 1;
            });

        $this->assertSame(1, Empleado::where('patrono_id', $patrono->id)->count());

        $empleado = Empleado::where('patrono_id', $patrono->id)
            ->where('cedula', 'V-30303030')
            ->firstOrFail();

        $this->assertSame('Ana Actualizada', $empleado->nombres);
        $this->assertEqualsWithDelta(500.0, (float) $empleado->salario_base, 0.01);
        $this->assertSame('2024-02-01', $empleado->fecha_ingreso->toDateString());
        $this->assertSame('Suspendido', $empleado->estatus);
        $this->assertSame('Gerente', $empleado->cargo);
    }

    public function test_conserva_las_columnas_ausentes_al_actualizar(): void
    {
        [$patrono, $empleado] = $this->crearPatronoEmpleado();

        $empleado->update([
            'telefono' => '0414-1234567',
            'correo' => 'ana@example.com',
            'cargo' => 'Analista',
        ]);

        $archivo = $this->archivoOds(
            ['Cédula', 'Nombres', 'Apellidos', 'Fecha de Ingreso', 'Tipo de Contrato', 'Tipo de Salario', 'Salario Base', 'Moneda'],
            [
                ['V-30303030', 'Ana', 'Pérez', '15/03/2024', 'Indeterminado', 'Fijo', '450', 'USD'],
            ]
        );

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo])
            ->assertRedirect(route('empleados.index'))
            ->assertSessionHas('success');

        $empleado->refresh();

        $this->assertEqualsWithDelta(450.0, (float) $empleado->salario_base, 0.01);
        $this->assertSame('0414-1234567', $empleado->telefono);
        $this->assertSame('ana@example.com', $empleado->correo);
        $this->assertSame('Analista', $empleado->cargo);
    }

    public function test_omite_las_cedulas_repetidas_dentro_del_archivo(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $archivo = $this->archivoOds(
            ['Cédula', 'Nombres', 'Apellidos', 'Fecha de Ingreso', 'Tipo de Contrato', 'Tipo de Salario', 'Salario Base', 'Moneda'],
            [
                ['V-77777777', 'Primero', 'Empleado', '01/02/2024', 'Indeterminado', 'Fijo', '500', 'USD'],
                ['V-77777777', 'Segundo', 'Empleado', '01/02/2024', 'Indeterminado', 'Fijo', '600', 'USD'],
            ]
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo]);

        $respuesta->assertRedirect(route('empleados.index'))
            ->assertSessionHas('info')
            ->assertSessionHas('importacion_detalle', function (array $detalle): bool {
                return $detalle['omitidos'] === ['V-77777777'];
            });

        $this->assertSame(
            'Primero',
            Empleado::where('patrono_id', $patrono->id)->where('cedula', 'V-77777777')->value('nombres')
        );
    }

    public function test_reporte_errores_por_fila_y_continua_con_las_validas(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $archivo = $this->archivoOds(
            ['Cédula', 'Nombres', 'Apellidos', 'Fecha de Ingreso', 'Tipo de Contrato', 'Tipo de Salario', 'Salario Base', 'Moneda'],
            [
                ['V-11111111', 'Valido', 'Empleado', '01/02/2024', 'Indeterminado', 'Fijo', '500', 'USD'],
                ['V-22222222', 'Invalido', 'Empleado', '01/02/2024', 'Permanente', 'Fijo', '500', 'USD'],
            ]
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo]);

        $respuesta->assertRedirect(route('empleados.index'))
            ->assertSessionHas('info')
            ->assertSessionHas('importacion_detalle', function (array $detalle): bool {
                return count($detalle['errores']) === 1
                    && str_contains($detalle['errores'][0], 'Fila 3')
                    && str_contains($detalle['errores'][0], 'Tipo de Contrato');
            });

        $this->assertTrue(
            Empleado::where('patrono_id', $patrono->id)->where('cedula', 'V-11111111')->exists()
        );
        $this->assertFalse(
            Empleado::where('patrono_id', $patrono->id)->where('cedula', 'V-22222222')->exists()
        );
    }

    public function test_rechaza_un_archivo_sin_las_columnas_obligatorias(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $archivo = $this->archivoOds(
            ['Cédula', 'Nombres'],
            [['V-11111111', 'Incompleto']]
        );

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo]);

        $respuesta->assertRedirect(route('empleados.index'))
            ->assertSessionHas('error', function (string $mensaje): bool {
                return str_contains($mensaje, 'Faltan columnas obligatorias');
            });

        $this->assertSame(1, Empleado::where('patrono_id', $patrono->id)->count());
    }

    public function test_importa_fechas_nativas_de_la_hoja_de_calculo(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $archivo = $this->archivoOds(
            ['Cédula', 'Nombres', 'Apellidos', 'Fecha de Ingreso', 'Tipo de Contrato', 'Tipo de Salario', 'Salario Base', 'Moneda'],
            [
                [
                    'V-55555555', 'Fecha', 'Nativa', new \DateTimeImmutable('2024-06-01'),
                    'Indeterminado', 'Fijo', 500, 'USD',
                ],
            ]
        );

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo])
            ->assertRedirect(route('empleados.index'))
            ->assertSessionHas('success');

        $empleado = Empleado::where('patrono_id', $patrono->id)
            ->where('cedula', 'V-55555555')
            ->firstOrFail();

        $this->assertSame('2024-06-01', $empleado->fecha_ingreso->toDateString());
    }

    public function test_el_listado_muestra_el_detalle_de_la_importacion(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $this->actingAs(User::factory()->create())
            ->withSession([
                'patrono_id' => $patrono->id,
                'importacion_detalle' => [
                    'errores' => ['Fila 3: Tipo de Contrato no válido.'],
                    'errores_total' => 1,
                    'omitidos' => ['V-30303030'],
                    'omitidos_total' => 1,
                    'actualizados' => ['V-40404040'],
                    'actualizados_total' => 1,
                ],
            ])
            ->get(route('empleados.index'))
            ->assertOk()
            ->assertSee('Cédulas omitidas')
            ->assertSee('V-30303030')
            ->assertSee('Empleados actualizados')
            ->assertSee('V-40404040')
            ->assertSee('Fila 3: Tipo de Contrato no válido.');
    }

    public function test_importa_capitalizacion_de_intereses_cuando_se_autoriza(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $archivo = $this->archivoOds(
            [
                'Cédula', 'Nombres', 'Apellidos', 'Fecha de Ingreso', 'Tipo de Contrato',
                'Tipo de Salario', 'Salario Base', 'Moneda', 'Capitaliza Intereses',
                'Capitalización Autorizada Desde', 'Capitalización Documento',
            ],
            [
                [
                    'V-66666666', 'Con', 'Capitalización', '01/02/2024', 'Indeterminado',
                    'Fijo', '500', 'USD', 'Sí', '15/03/2024', 'Carta firmada',
                ],
            ]
        );

        $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), ['archivo' => $archivo])
            ->assertRedirect(route('empleados.index'))
            ->assertSessionHas('success');

        $empleado = Empleado::where('patrono_id', $patrono->id)
            ->where('cedula', 'V-66666666')
            ->firstOrFail();

        $this->assertTrue($empleado->capitaliza_intereses);
        $this->assertSame('2024-03-15', $empleado->capitalizacion_autorizada_desde?->toDateString());
        $this->assertSame('Carta firmada', $empleado->capitalizacion_documento);
    }

    public function test_rechaza_archivos_que_no_son_ods(): void
    {
        [$patrono] = $this->crearPatronoEmpleado();

        $respuesta = $this->actingAs(User::factory()->create())
            ->withSession(['patrono_id' => $patrono->id])
            ->post(route('empleados.importar'), [
                'archivo' => UploadedFile::fake()->create('empleados.csv', 10, 'text/csv'),
            ]);

        $respuesta->assertSessionHasErrors('archivo');
        $this->assertSame(1, Empleado::where('patrono_id', $patrono->id)->count());
    }

    private function crearPatrono(string $rif, string $razonSocial): Patrono
    {
        return Patrono::create([
            'rif' => $rif,
            'razon_social' => $razonSocial,
            'estado' => 'Activo',
        ]);
    }

    /** @return array{0: Patrono, 1: Empleado} */
    private function crearPatronoEmpleado(): array
    {
        $patrono = $this->crearPatrono('J-30303030-1', 'Patrono de prueba');

        $empleado = Empleado::create([
            'patrono_id' => $patrono->id,
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'cedula' => 'V-30303030',
            'fecha_ingreso' => '2024-03-15',
            'tipo_contrato' => 'Indeterminado',
            'tipo_salario' => 'Fijo',
            'salario_base' => 300,
            'salario_inicial' => 300,
            'moneda' => 'USD',
            'estatus' => 'Activo',
            'dias_descanso' => '6,7',
        ]);

        return [$patrono, $empleado];
    }

    /**
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, mixed>>  $filas
     */
    private function archivoOds(array $encabezados, array $filas): UploadedFile
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Empleados');

        foreach ($encabezados as $indice => $titulo) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($indice + 1).'1', $titulo);
        }

        foreach ($filas as $numeroFila => $fila) {
            foreach ($fila as $indice => $valor) {
                $hoja->setCellValue(
                    Coordinate::stringFromColumnIndex($indice + 1).($numeroFila + 2),
                    $valor
                );
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'empleados').'.ods';
        (new Ods($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return new UploadedFile(
            $ruta,
            'empleados.ods',
            'application/vnd.oasis.opendocument.spreadsheet',
            null,
            true
        );
    }

    private function cargarOds(string $contenido): Spreadsheet
    {
        $ruta = tempnam(sys_get_temp_dir(), 'empleados').'.ods';
        file_put_contents($ruta, $contenido);

        $libro = IOFactory::load($ruta);

        @unlink($ruta);

        return $libro;
    }
}
