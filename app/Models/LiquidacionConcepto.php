<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionConcepto extends Model
{
    protected $table = 'liquidacion_conceptos';

    protected $fillable = [
        'liquidacion_id',
        'tipo',
        'orden',
        'concepto',
        'base_legal',
        'dias',
        'salario_diario',
        'tipo_salario',
        'importe',
        'editable',
    ];

    protected function casts(): array
    {
        return [
            'tipo'           => 'string',
            'orden'          => 'integer',
            'dias'           => 'decimal:2',
            'salario_diario' => 'decimal:2',
            'tipo_salario'   => 'string',
            'importe'        => 'decimal:2',
            'editable'       => 'boolean',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function esAsignacion(): bool
    {
        return $this->tipo === 'asignacion';
    }

    public function signo(): string
    {
        return $this->esAsignacion() ? '+' : '-';
    }
}