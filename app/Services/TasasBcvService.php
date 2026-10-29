<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TasaBcv;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Servicio de tasas de cambio del BCV.
 *
 * - Almacena la tasa USD (venta) por día en `tasas_bcv`.
 * - Puede importar el archivo .xls del BCV: una hoja por día cuyo nombre es
 *   DDMMYYYY; la fila de la moneda USD (habitualmente fila 15, columna B)
 *   contiene la tasa de venta en la columna G ("Venta (ASK)").
 */
class TasasBcvService
{
    /**
     * Última tasa publicada en o antes de una fecha (hacia atrás en el tiempo).
     */
    public function tasaEn(CarbonInterface $fecha): ?float
    {
        $tasa = TasaBcv::where('fecha', '<=', $fecha->toDateString())
            ->orderByDesc('fecha')
            ->first();

        return $tasa ? (float) $tasa->tasa_usd : null;
    }

    /**
     * Importa tasas desde un archivo Excel del BCV (.xls/.xlsx).
     *
     * Recorre cada hoja; si el nombre coincide con DDMMYYYY y se localiza la
     * fila de la moneda USD, guarda la tasa de venta (columna G).
     *
     * @return array{procesadas:int, insertadas:int, errores:array}
     */
    public function importarExcel(string $ruta): array
    {
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($ruta);

        $procesadas = 0;
        $insertadas = 0;
        $errores = [];

        foreach ($spreadsheet->getSheetNames() as $nombreHoja) {
            $fecha = $this->parsearFechaHoja($nombreHoja);
            if (! $fecha) {
                $errores[] = "Hoja '{$nombreHoja}': nombre no corresponde a una fecha DDMMYYYY.";

                continue;
            }

            $hoja = $spreadsheet->getSheetByName($nombreHoja);
            $tasa = $this->tasaUsdDeHoja($hoja);

            if ($tasa === null || $tasa <= 0) {
                $errores[] = "Hoja '{$nombreHoja}': no se encontró la tasa de venta USD.";

                continue;
            }

            $procesadas++;
            $this->guardarTasa($fecha, $tasa, 'excel');
            $insertadas++;
        }

        return compact('procesadas', 'insertadas', 'errores');
    }

    /**
     * Interpreta el nombre de una hoja como fecha DDMMYYYY.
     */
    public function parsearFechaHoja(string $nombreHoja): ?Carbon
    {
        $nombreHoja = trim($nombreHoja);

        if (strlen($nombreHoja) !== 8 || ! ctype_digit($nombreHoja)) {
            return null;
        }

        $dia = (int) substr($nombreHoja, 0, 2);
        $mes = (int) substr($nombreHoja, 2, 2);
        $anio = (int) substr($nombreHoja, 4, 4);

        if (! checkdate($mes, $dia, $anio)) {
            return null;
        }

        return Carbon::create($anio, $mes, $dia, 0, 0, 0);
    }

    /**
     * Localiza la fila de la moneda USD (columna B) y devuelve la tasa de
     * venta (columna G). Recorre hasta la fila 60 para robustez.
     */
    private function tasaUsdDeHoja($hoja): ?float
    {
        for ($fila = 1; $fila <= 60; $fila++) {
            $codigo = $hoja->getCell("B{$fila}")->getValue();
            if (is_string($codigo) && strtoupper(trim($codigo)) === 'USD') {
                $valor = $hoja->getCell("G{$fila}")->getValue();

                if (is_string($valor)) {
                    return parsear_numero($valor);
                }
                if (is_numeric($valor)) {
                    return (float) $valor;
                }

                return null;
            }
        }

        return null;
    }

    /**
     * Guarda una tasa (update-or-insert) con su fuente.
     */
    public function guardarTasa(CarbonInterface $fecha, float $tasa, string $fuente = 'manual'): TasaBcv
    {
        return TasaBcv::updateOrCreate(
            ['fecha' => $fecha->toDateString()],
            ['tasa_usd' => round($tasa, 6), 'fuente' => $fuente]
        );
    }
}
