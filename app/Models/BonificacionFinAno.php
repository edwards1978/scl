<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonificacionFinAno extends Model
{
    protected $table = 'bonificaciones_fin_ano';

    protected $fillable = [
        'patrono_id',
        'empleado_id',
        'anio',
        'periodo_inicio',
        'periodo_fin',
        'meses_laborados',
        'dias_base',
        'dias_a_pagar',
        'tipo_base',
        'salario_mensual_usd',
        'salario_mensual_bs',
        'salario_diario_usd',
        'salario_diario_bs',
        'bono_usd',
        'bono_bs',
        'tasa_cambio',
        'estatus',
        'fecha_aprobacion',
        'fecha_emision',
        'asignaciones',
        'deducciones',
        'total_asignaciones',
        'total_deducciones',
        'neto',
        'moneda',
    ];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
            'meses_laborados' => 'decimal:2',
            'dias_base' => 'integer',
            'dias_a_pagar' => 'decimal:2',
            'salario_mensual_usd' => 'decimal:2',
            'salario_mensual_bs' => 'decimal:2',
            'salario_diario_usd' => 'decimal:2',
            'salario_diario_bs' => 'decimal:2',
            'bono_usd' => 'decimal:2',
            'bono_bs' => 'decimal:2',
            'tasa_cambio' => 'decimal:4',
            'fecha_aprobacion' => 'date',
            'fecha_emision' => 'date',
            'asignaciones' => 'array',
            'deducciones' => 'array',
            'total_asignaciones' => 'decimal:2',
            'total_deducciones' => 'decimal:2',
            'neto' => 'decimal:2',
        ];
    }

    public function patrono(): BelongsTo
    {
        return $this->belongsTo(Patrono::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
