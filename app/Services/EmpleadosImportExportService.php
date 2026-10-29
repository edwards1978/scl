<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StoreEmpleadoRequest;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Rules\CedulaVenezolanaRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Importación y exportación de empleados en formato OpenDocument (.ods).
 *
 * El archivo usa una hoja "Empleados" con una fila de encabezados en la fila 1.
 * La importación crea los empleados nuevos y actualiza los ya registrados en
 * el patrono usando la cédula como clave, de modo que exportar, editar el
 * archivo y volver a importarlo permite actualizaciones masivas. Las cédulas
 * repetidas dentro del mismo archivo se omiten y se reportan al usuario.
 */
class EmpleadosImportExportService
{
    public function __construct(
        private SueldosService $sueldos = new SueldosService,
        private PrestacionesSocialesService $prestaciones = new PrestacionesSocialesService,
    ) {}

    /** Columnas del archivo, en el orden en que se exportan. */
    public const COLUMNAS = [
        'cedula' => 'Cédula',
        'nombres' => 'Nombres',
        'apellidos' => 'Apellidos',
        'fecha_nacimiento' => 'Fecha de Nacimiento',
        'genero' => 'Género',
        'telefono' => 'Teléfono',
        'correo' => 'Correo',
        'direccion' => 'Dirección',
        'fecha_ingreso' => 'Fecha de Ingreso',
        'cargo' => 'Cargo',
        'departamento' => 'Departamento',
        'tipo_contrato' => 'Tipo de Contrato',
        'tipo_salario' => 'Tipo de Salario',
        'jornada_laboral' => 'Jornada Laboral',
        'salario_base' => 'Salario Base',
        'salario_inicial' => 'Salario Inicial',
        'moneda' => 'Moneda',
        'estatus' => 'Estatus',
        'dias_descanso' => 'Días de Descanso',
        'capitaliza_intereses' => 'Capitaliza Intereses',
        'capitalizacion_autorizada_desde' => 'Capitalización Autorizada Desde',
        'capitalizacion_documento' => 'Capitalización Documento',
    ];

    /** Encabezados que deben existir para poder importar. */
    private const OBLIGATORIAS = [
        'cedula',
        'nombres',
        'apellidos',
        'fecha_ingreso',
        'tipo_contrato',
        'tipo_salario',
        'salario_base',
        'moneda',
    ];

    /** Alias aceptados para los encabezados (además del nombre de la columna). */
    private const ALIAS = [
        'cedula' => ['cedula de identidad', 'ci', 'documento de identidad'],
        'nombres' => ['nombre'],
        'apellidos' => ['apellido'],
        'fecha_nacimiento' => ['fecha nacimiento', 'nacimiento'],
        'genero' => ['sexo'],
        'telefono' => ['telefono de contacto', 'celular'],
        'correo' => ['correo electronico', 'email', 'e mail'],
        'direccion' => ['domicilio'],
        'fecha_ingreso' => ['fecha ingreso', 'ingreso'],
        'cargo' => ['puesto'],
        'departamento' => ['area'],
        'tipo_contrato' => ['tipo contrato', 'contrato'],
        'tipo_salario' => ['tipo salario'],
        'jornada_laboral' => ['jornada'],
        'salario_base' => ['salario', 'sueldo', 'sueldo base'],
        'salario_inicial' => ['sueldo inicial'],
        'moneda' => ['divisa'],
        'estatus' => ['estado'],
        'dias_descanso' => ['dias descanso', 'descanso'],
        'capitaliza_intereses' => ['capitaliza'],
        'capitalizacion_autorizada_desde' => ['autorizacion desde', 'fecha autorizacion'],
        'capitalizacion_documento' => ['documento de capitalizacion', 'documento'],
    ];

    /** Anchos de columna del archivo. */
    private const ANCHOS = [
        'cedula' => 14,
        'nombres' => 22,
        'apellidos' => 22,
        'fecha_nacimiento' => 16,
        'genero' => 9,
        'telefono' => 16,
        'correo' => 26,
        'direccion' => 36,
        'fecha_ingreso' => 16,
        'cargo' => 20,
        'departamento' => 20,
        'tipo_contrato' => 16,
        'tipo_salario' => 14,
        'jornada_laboral' => 26,
        'salario_base' => 15,
        'salario_inicial' => 15,
        'moneda' => 10,
        'estatus' => 12,
        'dias_descanso' => 16,
        'capitaliza_intereses' => 18,
        'capitalizacion_autorizada_desde' => 24,
        'capitalizacion_documento' => 30,
    ];

    private const MAXIMO_FILAS = 10000;

    /** Mensajes en español para la validación de cada fila importada. */
    private const MENSAJES = [
        'nombres.required' => 'los nombres son obligatorios.',
        'nombres.max' => 'los nombres superan el máximo de 255 caracteres.',
        'apellidos.required' => 'los apellidos son obligatorios.',
        'apellidos.max' => 'los apellidos superan el máximo de 255 caracteres.',
        'fecha_ingreso.required' => 'la fecha de ingreso es obligatoria.',
        'fecha_ingreso.date' => 'la fecha de ingreso no es válida.',
        'fecha_nacimiento.date' => 'la fecha de nacimiento no es válida.',
        'fecha_nacimiento.before' => 'la fecha de nacimiento debe ser anterior a hoy.',
        'correo.email' => 'el correo no es válido.',
        'correo.max' => 'el correo supera el máximo de 255 caracteres.',
        'tipo_contrato.required' => 'el tipo de contrato es obligatorio.',
        'tipo_salario.required' => 'el tipo de salario es obligatorio.',
        'salario_base.required' => 'el salario base es obligatorio.',
        'salario_base.numeric' => 'el salario base debe ser numérico.',
        'salario_base.min' => 'el salario base no puede ser negativo.',
        'moneda.required' => 'la moneda es obligatoria.',
        'capitalizacion_autorizada_desde.required_if' => 'debe indicar la fecha de autorización de la capitalización.',
        'capitalizacion_autorizada_desde.before_or_equal' => 'la fecha de autorización de la capitalización no puede ser futura.',
        'capitalizacion_documento.required_if' => 'debe indicar el documento de autorización de la capitalización.',
    ];

    /** Exporta los empleados del patrono (opcionalmente filtrados por búsqueda). */
    public function exportar(Patrono $patrono, ?string $buscar = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SCL')
            ->setTitle('Empleados')
            ->setSubject('Empleados de '.$patrono->razon_social);

        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Empleados');

        $this->escribirEncabezados($hoja);
        $this->aplicarAnchos($hoja);

        $empleados = Empleado::where('patrono_id', $patrono->id)
            ->when($buscar, function ($query, $texto) {
                $query->where(function ($q) use ($texto) {
                    $q->where('nombres', 'like', "%{$texto}%")
                        ->orWhere('apellidos', 'like', "%{$texto}%")
                        ->orWhere('cedula', 'like', "%{$texto}%");
                });
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->cursor();

        $fila = 2;
        foreach ($empleados as $empleado) {
            $this->escribirEmpleado($hoja, $fila, $empleado);
            $fila++;
        }

        $ultimaFila = max(1, $fila - 1);
        $ultimaColumna = Coordinate::stringFromColumnIndex(count(self::COLUMNAS));

        $hoja->getStyle("A1:{$ultimaColumna}{$ultimaFila}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ]);

        foreach (['salario_base', 'salario_inicial'] as $campo) {
            $columna = $this->columnaDe($campo);
            $hoja->getStyle("{$columna}2:{$columna}{$ultimaFila}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $hoja->getStyle("A1:{$ultimaColumna}1")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $this->agregarHojaInstrucciones(
            $spreadsheet,
            $patrono,
            'INSTRUCCIONES PARA ACTUALIZAR EMPLEADOS (.ods)',
            'Este archivo contiene los empleados registrados en el patrono. Modifique los datos de las filas que desee actualizar y vuelva a importarlo.'
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** Genera una plantilla vacía con los encabezados y una hoja de instrucciones. */
    public function plantilla(Patrono $patrono): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SCL')
            ->setTitle('Plantilla de empleados')
            ->setSubject('Plantilla para importar empleados de '.$patrono->razon_social);

        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Empleados');
        $this->escribirEncabezados($hoja);
        $this->aplicarAnchos($hoja);
        $hoja->getStyle('A1:'.Coordinate::stringFromColumnIndex(count(self::COLUMNAS)).'1')
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $this->agregarHojaInstrucciones(
            $spreadsheet,
            $patrono,
            'PLANTILLA PARA IMPORTAR EMPLEADOS (.ods)'
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Agrega la hoja "Instrucciones" compartida por la exportación y la plantilla.
     */
    private function agregarHojaInstrucciones(
        Spreadsheet $spreadsheet,
        Patrono $patrono,
        string $titulo,
        ?string $nota = null,
    ): void {
        $instrucciones = $spreadsheet->createSheet();
        $instrucciones->setTitle('Instrucciones');

        $lineas = [
            $titulo,
            '',
            'Patrono: '.$patrono->razon_social.' ('.$patrono->rif.')',
            '',
        ];

        if ($nota !== null) {
            $lineas[] = $nota;
            $lineas[] = '';
        }

        $lineas = array_merge($lineas, [
            '1. No modifique los encabezados de la hoja "Empleados".',
            '2. Cada fila representa un empleado. No repita la cédula dentro del archivo.',
            '3. Si la cédula ya está registrada en este patrono, el empleado se actualiza con los datos del archivo; si no existe, se crea.',
            '4. Para actualizaciones masivas: exporte los empleados, modifique los datos y vuelva a importar el archivo.',
            '5. Columnas obligatorias: Cédula, Nombres, Apellidos, Fecha de Ingreso, Tipo de Contrato, Tipo de Salario, Salario Base y Moneda.',
            '6. Fechas: use el formato DD/MM/AAAA (también se acepta AAAA-MM-DD).',
            '7. Tipo de Contrato: Determinado, Indeterminado u Obra.',
            '8. Tipo de Salario: Fijo, Variable o Mixto.',
            '9. Moneda: BS (bolívares) o USD (dólares).',
            '10. Estatus: Activo, Inactivo o Suspendido. Si se deja vacío se usa Activo.',
            '11. Género: M (masculino) o F (femenino).',
            '12. Días de Descanso: días separados por coma (ej. 6,7). Si se deja vacío se usa 6,7.',
            '13. Capitaliza Intereses: Sí o No. Si es Sí, complete "Capitalización Autorizada Desde" y "Capitalización Documento".',
            '14. Salario Inicial es opcional; si se deja vacío se usa el Salario Base.',
            '15. En empleados existentes solo se actualizan las columnas incluidas en el archivo; las columnas ausentes conservan su valor actual.',
        ]);

        foreach ($lineas as $indice => $linea) {
            $instrucciones->setCellValue('A'.($indice + 1), $linea);
        }

        $instrucciones->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $instrucciones->getStyle('A3')->getFont()->setBold(true);
        $instrucciones->getColumnDimension('A')->setWidth(110);
    }

    /**
     * Importa los empleados de un archivo .ods en el patrono indicado.
     *
     * Si la cédula ya existe en el patrono, actualiza el empleado con las
     * columnas presentes en el archivo. Si no existe, lo crea. Las cédulas
     * repetidas dentro del archivo se omiten para evitar ambigüedad.
     *
     * @return array{
     *     creados:int,
     *     actualizados:int,
     *     omitidos:int,
     *     errores:array<int, string>,
     *     cedulas_omitidas:array<int, string>,
     *     cedulas_actualizadas:array<int, string>
     * }
     */
    public function importar(string $ruta, Patrono $patrono): array
    {
        $hoja = $this->abrirHoja($ruta);
        $columnas = $this->mapearColumnas($hoja);
        $camposEnArchivo = array_flip(array_keys($columnas));

        $ultimaFila = $hoja->getHighestDataRow();

        if ($ultimaFila > self::MAXIMO_FILAS + 1) {
            throw new InvalidArgumentException('El archivo supera el máximo de '.number_format(self::MAXIMO_FILAS, 0, ',', '.').' filas de datos.');
        }

        $empleadosExistentes = Empleado::where('patrono_id', $patrono->id)
            ->get()
            ->keyBy('cedula');

        $cedulasEnArchivo = [];

        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $errores = [];
        $cedulasOmitidas = [];
        $cedulasActualizadas = [];

        for ($fila = 2; $fila <= $ultimaFila; $fila++) {
            $valores = [];

            foreach ($columnas as $campo => $indiceColumna) {
                $valores[$campo] = $hoja
                    ->getCell(Coordinate::stringFromColumnIndex($indiceColumna).$fila)
                    ->getValue();
            }

            if ($this->filaVacia($valores)) {
                continue;
            }

            try {
                $datos = $this->normalizarFila($valores);
            } catch (InvalidArgumentException $e) {
                $errores[] = "Fila {$fila}: {$e->getMessage()}";

                continue;
            }

            $cedula = $datos['cedula'];

            if (isset($cedulasEnArchivo[$cedula])) {
                $omitidos++;
                $cedulasOmitidas[] = $cedula;

                continue;
            }

            $validator = Validator::make($datos, StoreEmpleadoRequest::reglas(), self::MENSAJES);

            if ($validator->fails()) {
                $errores[] = "Fila {$fila} ({$cedula}): ".implode(' ', $validator->errors()->all());

                continue;
            }

            $cedulasEnArchivo[$cedula] = true;

            $empleado = $empleadosExistentes->get($cedula);

            if ($empleado instanceof Empleado) {
                $cambios = array_intersect_key($datos, $camposEnArchivo);

                if (array_key_exists('salario_inicial', $cambios)) {
                    $cambios['salario_inicial'] ??= $cambios['salario_base'];
                }

                if (array_key_exists('capitaliza_intereses', $cambios) && ! $cambios['capitaliza_intereses']) {
                    $cambios['capitalizacion_autorizada_desde'] = null;
                    $cambios['capitalizacion_documento'] = null;
                }

                $salarioAnterior = (float) $empleado->salario_base;
                $monedaAnterior = (string) $empleado->moneda;
                $inicialEnArchivo = array_key_exists('salario_inicial', $cambios) && $cambios['salario_inicial'] !== null;

                $cambioSalarial = (array_key_exists('salario_base', $cambios)
                        && (float) $cambios['salario_base'] !== $salarioAnterior)
                    || (array_key_exists('moneda', $cambios) && $cambios['moneda'] !== $monedaAnterior);

                try {
                    $empleado->update($cambios);

                    if ($cambioSalarial) {
                        if (! $inicialEnArchivo && $empleado->salario_inicial === null) {
                            $empleado->update(['salario_inicial' => $salarioAnterior]);
                        }

                        $this->sueldos->registrarCambio(
                            $empleado,
                            now(),
                            (float) $empleado->salario_base,
                            (string) $empleado->moneda,
                        );

                        if ($empleado->prestacionesSociales()->exists()) {
                            $this->prestaciones->regenerar($empleado);
                        }
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $errores[] = "Fila {$fila} ({$cedula}): no se pudo actualizar el empleado.";

                    continue;
                }

                $actualizados++;
                $cedulasActualizadas[] = $cedula;

                continue;
            }

            $datos['patrono_id'] = $patrono->id;
            $datos['salario_inicial'] ??= $datos['salario_base'];

            if (! $datos['capitaliza_intereses']) {
                $datos['capitalizacion_autorizada_desde'] = null;
                $datos['capitalizacion_documento'] = null;
            }

            try {
                $creado = Empleado::create($datos);
                $this->sueldos->registrarIngreso($creado);
            } catch (\Throwable $e) {
                report($e);
                $errores[] = "Fila {$fila} ({$cedula}): no se pudo guardar el empleado.";

                continue;
            }

            $creados++;
        }

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'cedulas_omitidas' => $cedulasOmitidas,
            'cedulas_actualizadas' => $cedulasActualizadas,
        ];
    }

    private function abrirHoja(string $ruta): Worksheet
    {
        try {
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($ruta);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('No se pudo leer el archivo .ods: '.$e->getMessage());
        }

        return $spreadsheet->getSheetByName('Empleados') ?? $spreadsheet->getSheet(0);
    }

    /**
     * Relaciona cada campo con el índice de su columna en el archivo.
     *
     * @return array<string, int>
     */
    private function mapearColumnas(Worksheet $hoja): array
    {
        $alias = [];

        foreach (self::COLUMNAS as $campo => $titulo) {
            $alias[$this->normalizarTexto($campo)] = $campo;
            $alias[$this->normalizarTexto($titulo)] = $campo;

            foreach (self::ALIAS[$campo] ?? [] as $texto) {
                $alias[$this->normalizarTexto($texto)] = $campo;
            }
        }

        $columnas = [];
        $ultimaColumna = Coordinate::columnIndexFromString($hoja->getHighestDataColumn(1));

        for ($indice = 1; $indice <= $ultimaColumna; $indice++) {
            $titulo = $this->normalizarTexto((string) $hoja
                ->getCell(Coordinate::stringFromColumnIndex($indice).'1')
                ->getValue());

            if ($titulo === '') {
                continue;
            }

            if (isset($alias[$titulo]) && ! isset($columnas[$alias[$titulo]])) {
                $columnas[$alias[$titulo]] = $indice;
            }
        }

        $faltantes = array_diff(self::OBLIGATORIAS, array_keys($columnas));

        if ($faltantes !== []) {
            $titulos = array_map(fn (string $campo): string => self::COLUMNAS[$campo], $faltantes);

            throw new InvalidArgumentException(
                'Faltan columnas obligatorias en la hoja "Empleados": '.implode(', ', $titulos).'.'
            );
        }

        return $columnas;
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function normalizarFila(array $valores): array
    {
        $texto = fn (string $campo): string => $this->textoCelda($valores[$campo] ?? null);
        $opcional = fn (string $campo): ?string => $texto($campo) !== '' ? $texto($campo) : null;

        $cedula = $this->normalizarCedula($texto('cedula'));

        if ($cedula === '') {
            throw new InvalidArgumentException('la cédula es obligatoria.');
        }

        $genero = $this->genero($opcional('genero'));

        return [
            'cedula' => $cedula,
            'nombres' => $texto('nombres'),
            'apellidos' => $texto('apellidos'),
            'fecha_nacimiento' => $this->fecha($valores['fecha_nacimiento'] ?? null, 'Fecha de Nacimiento'),
            'genero' => $genero,
            'telefono' => $opcional('telefono'),
            'correo' => $opcional('correo'),
            'direccion' => $opcional('direccion'),
            'fecha_ingreso' => $this->fecha($valores['fecha_ingreso'] ?? null, 'Fecha de Ingreso') ?? '',
            'cargo' => $opcional('cargo'),
            'departamento' => $opcional('departamento'),
            'tipo_contrato' => $this->tipoContrato($opcional('tipo_contrato')),
            'tipo_salario' => $this->tipoSalario($opcional('tipo_salario')),
            'jornada_laboral' => $opcional('jornada_laboral'),
            'salario_base' => $this->numero($valores['salario_base'] ?? null),
            'salario_inicial' => $this->numeroOpcional($valores['salario_inicial'] ?? null),
            'moneda' => $this->moneda($opcional('moneda')),
            'estatus' => $this->estatus($opcional('estatus')),
            'dias_descanso' => $this->diasDescanso($opcional('dias_descanso')),
            'capitaliza_intereses' => $this->booleano($valores['capitaliza_intereses'] ?? null),
            'capitalizacion_autorizada_desde' => $this->fecha($valores['capitalizacion_autorizada_desde'] ?? null, 'Capitalización Autorizada Desde'),
            'capitalizacion_documento' => $opcional('capitalizacion_documento'),
        ];
    }

    private function normalizarCedula(string $cedula): string
    {
        $cedula = strtoupper(preg_replace('/\s+/', '', $cedula) ?? '');

        if (preg_match('/^\d{6,8}$/', $cedula) === 1) {
            $cedula = 'V-'.$cedula;
        }

        return CedulaVenezolanaRule::formatear($cedula);
    }

    private function genero(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return match ($this->normalizarTexto($valor)) {
            'm', 'masculino', 'hombre' => 'M',
            'f', 'femenino', 'mujer' => 'F',
            default => throw new InvalidArgumentException("Género '{$valor}' no válido (use M o F)."),
        };
    }

    private function tipoContrato(?string $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return match ($this->normalizarTexto($valor)) {
            'determinado' => 'Determinado',
            'indeterminado' => 'Indeterminado',
            'obra' => 'Obra',
            default => throw new InvalidArgumentException("Tipo de Contrato '{$valor}' no válido (use Determinado, Indeterminado u Obra)."),
        };
    }

    private function tipoSalario(?string $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return match ($this->normalizarTexto($valor)) {
            'fijo' => 'Fijo',
            'variable' => 'Variable',
            'mixto' => 'Mixto',
            default => throw new InvalidArgumentException("Tipo de Salario '{$valor}' no válido (use Fijo, Variable o Mixto)."),
        };
    }

    private function moneda(?string $valor): string
    {
        if ($valor === null) {
            return 'BS';
        }

        return match ($this->normalizarTexto($valor)) {
            'bs', 'ves', 'bolivar', 'bolivares', 'b' => 'BS',
            'usd', 'dolar', 'dolares', 'us' => 'USD',
            default => throw new InvalidArgumentException("Moneda '{$valor}' no válida (use BS o USD)."),
        };
    }

    private function estatus(?string $valor): string
    {
        if ($valor === null) {
            return 'Activo';
        }

        return match ($this->normalizarTexto($valor)) {
            'activo' => 'Activo',
            'inactivo' => 'Inactivo',
            'suspendido' => 'Suspendido',
            default => throw new InvalidArgumentException("Estatus '{$valor}' no válido (use Activo, Inactivo o Suspendido)."),
        };
    }

    private function diasDescanso(?string $valor): string
    {
        if ($valor === null) {
            return '6,7';
        }

        $dias = preg_split('/[^1-7]+/', $valor, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $dias = array_values(array_unique($dias));
        sort($dias);

        if ($dias === [] || strlen(implode(',', $dias)) > 5) {
            throw new InvalidArgumentException("Días de Descanso '{$valor}' no válido (use números del 1 al 7 separados por coma, ej. 6,7).");
        }

        return implode(',', $dias);
    }

    private function fecha(mixed $valor, string $campo): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (is_numeric($valor) && (float) $valor > 0 && (float) $valor < 100000) {
            try {
                return Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
            } catch (\Throwable) {
                throw new InvalidArgumentException("{$campo}: fecha no válida.");
            }
        }

        $texto = trim((string) $valor);
        $texto = preg_replace(
            '/[ T]\d{1,2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/u',
            '',
            $texto
        ) ?? $texto;
        $texto = str_replace(['.', '-', ' '], '/', $texto);

        foreach (['d/m/Y', 'd/m/y', 'Y/m/d', 'Ymd', 'dmY'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            $ultimosErrores = \DateTimeImmutable::getLastErrors();

            if ($fecha !== false
                && ($ultimosErrores === false
                    || ($ultimosErrores['warning_count'] === 0 && $ultimosErrores['error_count'] === 0))) {
                return $fecha->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException("{$campo}: '{$texto}' no es una fecha válida (use DD/MM/AAAA).");
    }

    private function numero(mixed $valor): float
    {
        return $this->numeroOpcional($valor) ?? 0.0;
    }

    private function numeroOpcional(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = preg_replace('/[^\d,.\-]/u', '', trim((string) $valor)) ?? '';

        if ($texto === '') {
            return null;
        }

        return parsear_numero($texto);
    }

    private function booleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if (is_numeric($valor)) {
            return (float) $valor !== 0.0;
        }

        return in_array($this->normalizarTexto((string) $valor), ['si', 's', 'x', 'true', 'verdadero', '1'], true);
    }

    /** @param array<string, mixed> $valores */
    private function filaVacia(array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($this->textoCelda($valor) !== '') {
                return false;
            }
        }

        return true;
    }

    private function textoCelda(mixed $valor): string
    {
        if ($valor === null || is_bool($valor)) {
            return '';
        }

        if ($valor instanceof RichText) {
            return trim($valor->getPlainText());
        }

        return trim((string) $valor);
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = Str::ascii(mb_strtolower(trim($texto)));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '');
    }

    private function escribirEncabezados(Worksheet $hoja): void
    {
        $indice = 1;

        foreach (self::COLUMNAS as $titulo) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($indice).'1', $titulo);
            $indice++;
        }

        $ultimaColumna = Coordinate::stringFromColumnIndex(count(self::COLUMNAS));

        $hoja->getStyle("A1:{$ultimaColumna}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1F2937'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $hoja->getRowDimension(1)->setRowHeight(32);
        $hoja->freezePane('A2');
        $hoja->setAutoFilter("A1:{$ultimaColumna}1");
    }

    private function aplicarAnchos(Worksheet $hoja): void
    {
        $indice = 1;

        foreach (self::COLUMNAS as $campo => $titulo) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice))
                ->setWidth(self::ANCHOS[$campo] ?? 16);
            $indice++;
        }
    }

    private function escribirEmpleado(Worksheet $hoja, int $fila, Empleado $empleado): void
    {
        $valores = [
            'cedula' => $empleado->cedula,
            'nombres' => $empleado->nombres,
            'apellidos' => $empleado->apellidos,
            'fecha_nacimiento' => $empleado->fecha_nacimiento,
            'genero' => $empleado->genero,
            'telefono' => $empleado->telefono,
            'correo' => $empleado->correo,
            'direccion' => $empleado->direccion,
            'fecha_ingreso' => $empleado->fecha_ingreso,
            'cargo' => $empleado->cargo,
            'departamento' => $empleado->departamento,
            'tipo_contrato' => $empleado->tipo_contrato,
            'tipo_salario' => $empleado->tipo_salario,
            'jornada_laboral' => $empleado->jornada_laboral,
            'salario_base' => (float) $empleado->salario_base,
            'salario_inicial' => $empleado->salario_inicial !== null
                ? (float) $empleado->salario_inicial
                : null,
            'moneda' => $empleado->moneda,
            'estatus' => $empleado->estatus,
            'dias_descanso' => $empleado->dias_descanso,
            'capitaliza_intereses' => $empleado->capitaliza_intereses ? 'Sí' : 'No',
            'capitalizacion_autorizada_desde' => $empleado->capitalizacion_autorizada_desde,
            'capitalizacion_documento' => $empleado->capitalizacion_documento,
        ];

        $indice = 1;

        foreach (self::COLUMNAS as $campo => $titulo) {
            $coordenada = Coordinate::stringFromColumnIndex($indice).$fila;
            $valor = $valores[$campo] ?? null;

            if ($valor instanceof \DateTimeInterface) {
                $hoja->setCellValue($coordenada, $valor->format('d/m/Y'));
            } else {
                $hoja->setCellValue($coordenada, $valor);
            }

            $indice++;
        }
    }

    private function columnaDe(string $campo): string
    {
        $indice = array_search($campo, array_keys(self::COLUMNAS), true);

        return Coordinate::stringFromColumnIndex((int) $indice + 1);
    }
}
