<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class SalarioMinimo extends Model
{
    protected $table = 'salarios_minimos';

    protected $fillable = [
        'fecha_vigencia',
        'gaceta_oficial',
        'mensual_bs',
        'diario_bs',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vigencia' => 'date',
            'mensual_bs' => 'decimal:8',
            'diario_bs' => 'decimal:8',
        ];
    }

    /**
     * Salario mínimo vigente en o antes de una fecha (la fila más reciente
     * cuyo inicio de vigencia sea <= a $fecha). La fila sin límite superior
     * (la más nueva) queda vigente hasta hoy.
     */
    public static function vigenteEn(CarbonInterface $fecha): ?SalarioMinimo
    {
        return static::where('fecha_vigencia', '<=', $fecha->toDateString())
            ->orderByDesc('fecha_vigencia')
            ->first();
    }
}
