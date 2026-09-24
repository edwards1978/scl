<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SalarioMinimo;
use Illuminate\Database\Seeder;

class SalariosMinimosSeeder extends Seeder
{
    /**
     * Histórico de salarios mínimos en Venezuela. `mensual_bs`/`diario_bs`
     * se almacenan en Bs reexpresados (para los cálculos); el valor original
     * histórico se conserva en `nota`.
     */
    public function run(): void
    {
        $datos = [
            [
                'fecha_vigencia' => '2022-03-15',
                'gaceta_oficial' => 'N° 6.691 Ext.',
                'mensual_bs'     => 130.00,
                'diario_bs'      => 4.33,
                'nota'           => null,
            ],
            [
                'fecha_vigencia' => '2021-05-01',
                'gaceta_oficial' => 'N° 6.622 Ext.',
                'mensual_bs'     => 7.00,
                'diario_bs'      => 0.2333,
                'nota'           => 'Bs. 7.000.000,00 (Bs. 7,00 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2020-11-01',
                'gaceta_oficial' => 'N° 6.589 Ext.',
                'mensual_bs'     => 1.20,
                'diario_bs'      => 0.04,
                'nota'           => 'Bs. 1.200.000,00 (Bs. 1,20 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2020-05-01',
                'gaceta_oficial' => 'N° 6.532 Ext.',
                'mensual_bs'     => 0.40,
                'diario_bs'      => 0.0133,
                'nota'           => 'Bs. 400.000,00 (Bs. 0,40 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2020-01-01',
                'gaceta_oficial' => 'N° 6.502 Ext.',
                'mensual_bs'     => 0.25,
                'diario_bs'      => 0.0083,
                'nota'           => 'Bs. 250.000,00 (Bs. 0,25 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2019-11-01',
                'gaceta_oficial' => 'N° 6.484 Ext.',
                'mensual_bs'     => 0.15,
                'diario_bs'      => 0.005,
                'nota'           => 'Bs. 150.000,00 (Bs. 0,15 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2019-04-16',
                'gaceta_oficial' => 'N° 6.452 Ext.',
                'mensual_bs'     => 0.04,
                'diario_bs'      => 0.0013,
                'nota'           => 'Bs. 40.000,00 (Bs. 0,04 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2019-01-15',
                'gaceta_oficial' => 'N° 6.424 Ext.',
                'mensual_bs'     => 0.018,
                'diario_bs'      => 0.0006,
                'nota'           => 'Bs. 18.000,00 (Bs. 0,018 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2018-12-01',
                'gaceta_oficial' => 'N° 6.415 Ext.',
                'mensual_bs'     => 0.0045,
                'diario_bs'      => 0.00015,
                'nota'           => 'Bs. 4.500,00 (Bs. 0,0045 Reexpresados)',
            ],
            [
                'fecha_vigencia' => '2018-09-01',
                'gaceta_oficial' => 'N° 6.398 Ext.',
                'mensual_bs'     => 0.0018,
                'diario_bs'      => 0.00006,
                'nota'           => 'Bs. 1.800,00 (Bs. 0,0018 Reconversión)',
            ],
        ];

        foreach ($datos as $dato) {
            SalarioMinimo::updateOrCreate(
                ['fecha_vigencia' => $dato['fecha_vigencia']],
                $dato
            );
        }
    }
}