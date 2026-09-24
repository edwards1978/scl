<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TasaInteresPrestaciones;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Servicio de tasas de interés aplicables al cálculo de los intereses sobre
 * prestaciones sociales (BCV).
 *
 * Las tasas se almacenan por periodo (YYYY-MM) en `tasas_interes_prestaciones`.
 * El importador lee la hoja principal del archivo del BCV ("1_3_18.xlsx"): la
 * hoja cuya fila 2 dice "BANCO CENTRAL DE VENEZUELA" y fila 3 "TASA DE INTERÉS
 * APLICABLE AL CÁLCULO DE LOS INTERESES SOBRE PRESTACIONES SOCIALES". Los datos
 * inician en la fila 8: una celda A con un año de 4 dígitos abre un bloque de
 * meses; cada mes siguiente (columna A) lleva su tasa en la columna E
 * ("Promedio entre Activa y Pasiva").
 */
class TasasInteresService
{
    /** Texto esperado en la fila 2 (validación del archivo). */
    public const HEADER_ENTIDAD = 'BANCO CENTRAL DE VENEZUELA';

    /** Texto esperado en la fila 3 (validación del archivo). */
    public const HEADER_TITULO = 'TASA DE INTERÉS APLICABLE AL CÁLCULO DE LOS INTERESES SOBRE PRESTACIONES SOCIALES';

    /** Fila donde inician los datos (año + meses). */
    public const FILA_INICIO = 8;

    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5,
        'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9,
        'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    /**
     * Tasa de interés vigente para un periodo (YYYY-MM). Si no existe para ese
     * periodo exacto, devuelve la más reciente anterior o igual.
     */
    public function tasaEn(CarbonInterface $fecha): ?float
    {
        $periodo = $fecha->format('Y-m');

        $tasa = TasaInteresPrestaciones::where('periodo', '<=', $periodo)
            ->orderByDesc('periodo')
            ->first();

        return $tasa ? (float) $tasa->tasa_interes : null;
    }

    /**
     * Guarda (update-or-insert) una tasa por periodo.
     */
    public function guardarTasa(CarbonInterface $fecha, float $tasa, string $fuente = 'manual'): TasaInteresPrestaciones
    {
        return TasaInteresPrestaciones::updateOrCreate(
            ['periodo' => $fecha->format('Y-m')],
            ['tasa_interes' => round($tasa, 4), 'fuente' => $fuente]
        );
    }

    /**
     * Importa las tasas del archivo Excel del BCV (hoja principal).
     *
     * @return array{procesados:int, insertados:int, errores:array}
     */
    public function importarExcel(string $ruta): array
    {
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($ruta);

        $hoja = $this->localizarHojaPrincipal($spreadsheet);

        if (! $hoja) {
            return [
                'procesados' => 0,
                'insertados' => 0,
                'errores'    => ['No se encontró la hoja principal con el encabezado del BCV (filas 2 y 3).'],
            ];
        }

        $procesados = 0;
        $insertados = 0;
        $errores = [];

        $anio = null;
        $ultimaFila = $hoja->getHighestRow();

        for ($fila = self::FILA_INICIO; $fila <= $ultimaFila; $fila++) {
            $valorA = trim((string) $hoja->getCell("A{$fila}")->getValue());

            if ($valorA === '' || $this->esFinLectura($valorA)) {
                continue;
            }

            // Año de 4 dígitos abre un bloque de meses.
            if (preg_match('/^\d{4}$/', $valorA)) {
                $anio = (int) $valorA;

                continue;
            }

            $mes = $this->mesDeNombre($valorA);

            if (! $mes || $anio === null) {
                continue;
            }

            $tasa = $this->tasaDeFila($hoja, $fila);

            if ($tasa === null || $tasa < 0) {
                $errores[] = "Fila {$fila} ({$valorA} {$anio}): tasa no válida.";

                continue;
            }

            $procesados++;
            $this->guardarTasa(Carbon::create($anio, $mes, 1), $tasa, 'excel');
            $insertados++;
        }

        return compact('procesados', 'insertados', 'errores');
    }

    /**
     * Localiza la hoja que cumple la validación de encabezado (filas 2 y 3).
     */
    private function localizarHojaPrincipal($spreadsheet): ?\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $hoja) {
            $fila2 = strtoupper(trim((string) $hoja->getCell('A2')->getValue()));
            $fila3 = trim((string) $hoja->getCell('A3')->getValue());

            if (str_starts_with($fila2, self::HEADER_ENTIDAD) && $fila3 === self::HEADER_TITULO) {
                return $hoja;
            }
        }

        return null;
    }

    /**
     * Lee la tasa de interés de la columna E de una fila.
     */
    private function tasaDeFila($hoja, int $fila): ?float
    {
        $valor = $hoja->getCell("E{$fila}")->getValue();

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        if (is_string($valor) && trim($valor) !== '') {
            return parsear_numero($valor);
        }

        return null;
    }

    /**
     * Devuelve el número de mes (1-12) de un nombre en español, o null.
     */
    private function mesDeNombre(string $nombre): ?int
    {
        $clave = mb_strtolower($nombre);

        // Normaliza acentos: junio, JUNIO, Junio…
        $clave = str_replace(
            ['á', 'é', 'í', 'ó', 'ú'],
            ['a', 'e', 'i', 'o', 'u'],
            $clave
        );

        return self::MESES[$clave] ?? null;
    }

    /**
     * Indica si una celda marca el fin de la lectura (periodo parcial o fin).
     */
    private function esFinLectura(string $valor): bool
    {
        return in_array(strtoupper($valor), ['FIN', 'FIN DEL DOCUMENTO'], true)
            || preg_match('/^\d{1,2}\/\d{1,2}\s*-\s*\d{1,2}\/\d{1,2}$/', $valor) === 1;
    }
}
