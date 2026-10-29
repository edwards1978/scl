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
        'fecha_comprobante',
        'monto',
        'moneda',
        'tasa_cambio',
        'comprobante',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_comprobante' => 'date',
            'monto' => 'decimal:2',
            'tasa_cambio' => 'decimal:4',
            'moneda' => 'string',
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

    /** Número de comprobante derivado del identificador del pago. */
    public function numeroComprobante(): string
    {
        return 'INT-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Moneda en la que se expresa el equivalente del monto. */
    public function monedaEquivalente(): string
    {
        return $this->moneda === 'USD' ? 'Bs.' : 'USD';
    }

    /** Equivalente del monto en la otra moneda según la tasa BCV registrada. */
    public function montoEnOtraMoneda(): ?float
    {
        $tasa = (float) $this->tasa_cambio;

        if ($tasa <= 0) {
            return null;
        }

        return $this->moneda === 'USD'
            ? round((float) $this->monto * $tasa, 2)
            : round((float) $this->monto / $tasa, 2);
    }
}
