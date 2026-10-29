<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculoLiquidacionLiteralC extends Model
{
    public const TIPO_MENSUAL = 'mensual';

    public const TIPO_SEMANAL = 'semanal';

    public const TIPO_QUINCENAL = 'quincenal';

    public const TIPO_DIARIO = 'diario';

    public const MONEDA_USD = 'USD';

    public const MONEDA_BS = 'BS';

    protected $table = 'calculos_liquidacion_literal_c';

    protected $fillable = [
        'patrono_nombre',
        'patrono_rif',
        'trabajador_nombre',
        'trabajador_cedula',
        'cargo',
        'fecha_ingreso',
        'fecha_egreso',
        'tipo_salario',
        'salario',
        'moneda',
        'mostrar_equivalencia',
        'dias_utilidades',
        'anios_vacaciones_vencidas',
        'anios_utilidades_vencidas',
        'salario_diario_normal',
        'salario_diario_integral',
        'tasa_bcv',
        'anticipo',
        'intereses_pagados',
        'prestamo_saldo',
        'total_asignaciones',
        'total_deducciones',
        'neto',
        'conceptos',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_egreso' => 'date',
            'salario' => 'decimal:2',
            'mostrar_equivalencia' => 'boolean',
            'dias_utilidades' => 'integer',
            'anios_vacaciones_vencidas' => 'integer',
            'anios_utilidades_vencidas' => 'integer',
            'salario_diario_normal' => 'decimal:2',
            'salario_diario_integral' => 'decimal:2',
            'tasa_bcv' => 'decimal:6',
            'anticipo' => 'decimal:2',
            'intereses_pagados' => 'decimal:2',
            'prestamo_saldo' => 'decimal:2',
            'total_asignaciones' => 'decimal:2',
            'total_deducciones' => 'decimal:2',
            'neto' => 'decimal:2',
            'conceptos' => 'array',
        ];
    }

    /** @return array<string, string> */
    public static function tiposSalario(): array
    {
        return [
            self::TIPO_MENSUAL => 'Mensual',
            self::TIPO_SEMANAL => 'Semanal',
            self::TIPO_QUINCENAL => 'Quincenal',
            self::TIPO_DIARIO => 'Diario',
        ];
    }

    /** @return array<string, string> */
    public static function monedas(): array
    {
        return [
            self::MONEDA_USD => 'USD',
            self::MONEDA_BS => 'Bs.',
        ];
    }

    public function labelTipoSalario(): string
    {
        return self::tiposSalario()[$this->tipo_salario] ?? $this->tipo_salario;
    }

    public function labelMoneda(): string
    {
        return self::monedas()[$this->moneda] ?? $this->moneda;
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
