<?php

namespace Database\Seeders;

use App\Models\Feriado;
use App\Models\FeriadoFijo;
use App\Services\DiasFeriadosService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FeriadosSeeder extends Seeder
{
    /**
     * Días feriados fijos nacionales de Venezuela (Art. 184 LOTTT),
     * como [mes, dia, descripcion] para la tabla editable `feriados_fijos`.
     */
    protected array $fijos = [
        [1,  1,  'Año Nuevo'],
        [4,  19, 'Declaración de Independencia (1810)'],
        [5,  1,  'Día del Trabajador'],
        [6,  24, 'Batalla de Carabobo y Día del Ejército'],
        [7,  5,  'Día de la Independencia (1811)'],
        [7,  24, 'Natalicio del Libertador Simón Bolívar'],
        [10, 12, 'Día de la Resistencia Indígena'],
        [12, 24, 'Nochebuena'],
        [12, 25, 'Navidad'],
        [12, 31, 'Fin de Año'],
    ];

    public function run(): void
    {
        // 1) Poblar la tabla editable de fijos (se reemplaza el contenido).
        FeriadoFijo::query()->truncate();
        foreach ($this->fijos as [$mes, $dia, $descripcion]) {
            FeriadoFijo::create([
                'dia'    => $dia,
                'mes'    => $mes,
                'descripcion' => $descripcion,
                'activo' => true,
            ]);
        }

        // 2) Materializar el año vigente (y el siguiente como margen) vía servicio.
        $anioActual = (int) Carbon::now()->format('Y');
        $servicio = app(DiasFeriadosService::class);
        $servicio->materializarAnio($anioActual);
        $servicio->materializarAnio($anioActual + 1);
    }
}