<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sueldo extends Model
{
    protected $table = 'sueldos';

    protected $fillable = [
        'empleado_id',
        'periodo',
        'fecha_cierre',
        'moneda_sueldo',
        'monto_usd',
        'monto_bs',
        'tasa_bcv',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre' => 'date',
            'monto_usd' => 'decimal:2',
            'monto_bs' => 'decimal:2',
            'tasa_bcv' => 'decimal:6',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function getAnioAttribute(): string
    {
        return substr((string) $this->periodo, 0, 4);
    }

    public function getMesAttribute(): string
    {
        return substr((string) $this->periodo, 5, 2);
    }

    /** Etiqueta del periodo como "08/2026". */
    public function getLabelPeriodoAttribute(): string
    {
        return substr((string) $this->periodo, 5, 2).'/'.substr((string) $this->periodo, 0, 4);
    }
}
