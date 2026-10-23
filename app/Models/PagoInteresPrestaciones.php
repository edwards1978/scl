<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoInteresPrestaciones extends Model
{
    protected $table = 'pagos_interes_prestaciones';

    protected $fillable = [
        'patrono_id',
        'empleado_id',
        'fecha',
        'monto',
        'moneda',
        'tasa_cambio',
        'comprobante',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'        => 'date',
            'monto'        => 'decimal:2',
            'tasa_cambio'  => 'decimal:4',
            'moneda'       => 'string',
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
