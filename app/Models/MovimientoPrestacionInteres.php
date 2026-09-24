<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoPrestacionInteres extends Model
{
    protected $table = 'movimiento_prestaciones_intereses';

    protected $fillable = [
        'movimiento_prestacion_id',
        'fecha',
        'monto',
        'moneda',
        'tasa_cambio',
        'concepto',
    ];

    protected function casts(): array
    {
        return [
            'fecha'       => 'date',
            'monto'       => 'decimal:2',
            'tasa_cambio' => 'decimal:4',
            'moneda'      => 'string',
        ];
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoPrestaciones::class, 'movimiento_prestacion_id');
    }
}