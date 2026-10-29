<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioSalario extends Model
{
    protected $table = 'cambios_salarios';

    protected $fillable = [
        'empleado_id',
        'fecha_cambio',
        'monto',
        'moneda',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cambio' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
