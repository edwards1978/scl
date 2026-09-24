<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestacionSocial extends Model
{
    protected $table = 'prestaciones_sociales';

    protected $fillable = [
        'patrono_id',
        'empleado_id',
        'periodo',
        'fecha_cierre',
        'anios_servicio',
        'trimestre',
        'salario_integral_diario',
        'moneda',
        'tasa_interes',
        'saldo_inicial',
        'deposito_trimestre',
        'dias_deposito',
        'dias_adicionales',
        'dias_adicionales_cont',
        'interes_mes',
        'anticipos',
        'saldo_final',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre'            => 'date',
            'anios_servicio'          => 'integer',
            'trimestre'               => 'integer',
            'salario_integral_diario' => 'decimal:2',
            'moneda'                  => 'string',
            'tasa_interes'            => 'decimal:4',
            'saldo_inicial'           => 'decimal:2',
            'deposito_trimestre'      => 'decimal:2',
            'dias_deposito'           => 'integer',
            'dias_adicionales'        => 'decimal:2',
            'dias_adicionales_cont'   => 'integer',
            'interes_mes'             => 'decimal:2',
            'anticipos'               => 'decimal:2',
            'saldo_final'             => 'decimal:2',
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
