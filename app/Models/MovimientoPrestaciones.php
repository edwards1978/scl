<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovimientoPrestaciones extends Model
{
    protected $table = 'movimientos_prestaciones';

    public const TIPO_ANTICIPO = 'anticipo';

    public const TIPO_PRESTAMO = 'prestamo';

    public const MOTIVOS_ANTICIPO = ['vivienda', 'educacion', 'salud'];

    protected $fillable = [
        'patrono_id',
        'empleado_id',
        'tipo',
        'fecha',
        'fecha_comprobante',
        'concepto',
        'motivo',
        'monto',
        'moneda',
        'tasa_cambio',
        'plazo_meses',
        'interes_anual',
        'cuota',
        'saldo',
        'estatus',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_comprobante' => 'date',
            'monto' => 'decimal:2',
            'tasa_cambio' => 'decimal:4',
            'plazo_meses' => 'integer',
            'interes_anual' => 'decimal:4',
            'cuota' => 'decimal:2',
            'saldo' => 'decimal:2',
            'tipo' => 'string',
            'motivo' => 'string',
            'moneda' => 'string',
            'estatus' => 'string',
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

    public function intereses(): HasMany
    {
        return $this->hasMany(MovimientoPrestacionInteres::class, 'movimiento_prestacion_id')->latest('fecha');
    }

    public function esAnticipo(): bool
    {
        return $this->tipo === self::TIPO_ANTICIPO;
    }

    public function activo(): bool
    {
        return $this->estatus === 'activo';
    }

    public function labelTipo(): string
    {
        return $this->esAnticipo() ? 'Anticipo' : 'Préstamo';
    }

    public function labelMotivo(): ?string
    {
        return match ($this->motivo) {
            'vivienda' => 'Vivienda',
            'educacion' => 'Educación',
            'salud' => 'Salud',
            default => null,
        };
    }

    public function totalInteresesPagados(): float
    {
        return (float) $this->intereses()->sum('monto');
    }

    public function colorEstatus(): string
    {
        return match ($this->estatus) {
            'activo' => 'bg-blue-50 text-blue-700',
            'pagado' => 'bg-green-50 text-green-700',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
