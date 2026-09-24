<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Feriado;
use App\Models\FeriadoFijo;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cálculo y materialización de días feriados de Venezuela (LOTTT).
 *
 * - Feriados fijos: provienen de la tabla `feriados_fijos` (día/mes editable).
 * - Feriados variables: se calculan a partir del Domingo de Pascua (algoritmo
 *   de Gauss) y son los que pueden cambiar por su complejidad calendárica:
 *     - Lunes de Carnaval   : Domingo de Pascua - 48 días
 *     - Martes de Carnaval  : Domingo de Pascua - 47 días
 *     - Jueves Santo        : Domingo de Pascua -  3 días
 *     - Viernes Santo       : Domingo de Pascua -  2 días
 *
 * Los feriados se materializan (con fecha concreta) en la tabla `feriados`,
 * que es la que consume CalendarioLaboral.
 */
class DiasFeriadosService
{
    /** Días de resta respecto al Domingo de Pascua para cada feriado variable. */
    public const VARIABLES = [
        'Lunes de Carnaval'  => -48,
        'Martes de Carnaval' => -47,
        'Jueves Santo'       => -3,
        'Viernes Santo'      => -2,
    ];

    /**
     * Feriados fijos activos (editables por el usuario).
     */
    public function fijos(): Collection
    {
        return FeriadoFijo::query()->activos()->orden()->get();
    }

    /**
     * Feriados fijos (activos) de un año, como 'Y-m-d' => descripcion.
     */
    public function fijosDelAnio(int $anio): array
    {
        return $this->fijos()
            ->mapWithKeys(function (FeriadoFijo $f) use ($anio) {
                $fecha = Carbon::create($anio, $f->mes, $f->dia);

                return [$fecha->format('Y-m-d') => $f->descripcion];
            })
            ->all();
    }

    /**
     * Domingo de Pascua de un año vía algoritmo Computus (Gauss).
     * Respaldado por Carbon cuando esté disponible.
     */
    public function domingoDePascua(int $anio): Carbon
    {
        $base = Carbon::create($anio, 3, 21, 0, 0, 0);

        return $base->addDays($this->computus($anio));
    }

    /**
     * Feriados variables calculados de un año: 'Y-m-d' => descripcion.
     */
    public function variablesDelAnio(int $anio): array
    {
        $pascua = $this->domingoDePascua($anio);
        $result = [];

        foreach (self::VARIABLES as $descripcion => $resta) {
            $fecha = $pascua->copy()->addDays($resta);
            $result[$fecha->format('Y-m-d')] = $descripcion;
        }

        return $result;
    }

    /**
     * Todos los feriados de un año: 'Y-m-d' => ['descripcion', 'calculado'].
     */
    public function feriadosDelAnio(int $anio): array
    {
        return $this->fijosDelAnio($anio)
            ->merge($this->variablesDelAnio($anio));
    }

    /**
     * Materializa en la tabla `feriados` los feriados de un año (fijos + variables).
     * Re-escribe las filas del año para reflejar ediciones de fijos o cambios
     * en el cálculo de variables.
     */
    public function materializarAnio(int $anio): int
    {
        $desde = Carbon::create($anio, 1, 1)->format('Y-m-d');
        $hasta = Carbon::create($anio, 12, 31)->format('Y-m-d');

        Feriado::whereBetween('fecha', [$desde, $hasta])->delete();

        $filas = [];

        foreach ($this->fijosDelAnio($anio) as $fecha => $descripcion) {
            $filas[] = ['fecha' => $fecha, 'descripcion' => $descripcion, 'calculado' => false, 'created_at' => now(), 'updated_at' => now()];
        }

        foreach ($this->variablesDelAnio($anio) as $fecha => $descripcion) {
            $filas[] = ['fecha' => $fecha, 'descripcion' => $descripcion, 'calculado' => true, 'created_at' => now(), 'updated_at' => now()];
        }

        if (empty($filas)) {
            return 0;
        }

        return Feriado::insert($filas) ? count($filas) : 0;
    }

    /**
     * Garantiza que un año esté materializado y devuelve el set de fechas
     * (formato 'Y-m-d'). Cachea en memoria por request.
     */
    public function asegurarAnio(int $anio): array
    {
        static $cache = [];

        if (isset($cache[$anio])) {
            return $cache[$anio];
        }

        $this->materializarAnio($anio);

        $cache[$anio] = Feriado::whereYear('fecha', $anio)->pluck('fecha')->map(
            fn (?string $f) => Carbon::parse($f)->format('Y-m-d')
        )->all();

        return $cache[$anio];
    }

    /** Algoritmo Computus (Gauss): días después del 21-03 hasta Pascua. */
    private function computus(int $anio): int
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = ($h + $l - 7 * $m + 114) % 31 + 1;

        return (int) Carbon::create($anio, 3, 21)->diffInDays(Carbon::create($anio, $mes, $dia));
    }
}