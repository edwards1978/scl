<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use App\Rules\RifVenezolanoRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patrono extends Model
{
    use RegistraAuditoria;
    use SoftDeletes;

    public const DEPOSITO_LIQUIDACION_COMPLETO = 'completo';

    public const DEPOSITO_LIQUIDACION_PRORRATEADO = 'prorrateado';

    public const DEPOSITO_LIQUIDACION_NINGUNO = 'ninguno';

    protected $fillable = [
        'rif',
        'razon_social',
        'nombre_comercial',
        'registro_mercantil',
        'direccion_fiscal',
        'representante_legal',
        'telefono',
        'correo',
        'estado',
        'descuenta_ivss',
        'descuenta_paro_fijo',
        'descuenta_faov',
        'descuenta_inces',
        'mostrar_equivalencia_usd',
        'dias_bonificacion',
        'deposito_tipo',
        'deposito_liquidacion_trimestre',
        'anticipos_max_anio',
        'anticipos_pct_contabilidad',
        'anticipos_pct_fideicomiso',
        'anticipos_pct_fondo',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'string',
            'descuenta_ivss' => 'boolean',
            'descuenta_paro_fijo' => 'boolean',
            'descuenta_faov' => 'boolean',
            'descuenta_inces' => 'boolean',
            'mostrar_equivalencia_usd' => 'boolean',
            'dias_bonificacion' => 'integer',
            'deposito_tipo' => 'string',
            'deposito_liquidacion_trimestre' => 'string',
            'anticipos_max_anio' => 'integer',
            'anticipos_pct_contabilidad' => 'integer',
            'anticipos_pct_fideicomiso' => 'integer',
            'anticipos_pct_fondo' => 'integer',
        ];
    }

    /**
     * Opciones de depósito del trimestre en curso al momento de liquidar.
     *
     * @return array<string, string>
     */
    public static function opcionesDepositoLiquidacion(): array
    {
        return [
            self::DEPOSITO_LIQUIDACION_COMPLETO => 'Trimestre completo (15 días) — predeterminada',
            self::DEPOSITO_LIQUIDACION_PRORRATEADO => '5 días por cada mes efectivo del trimestre en curso',
            self::DEPOSITO_LIQUIDACION_NINGUNO => 'No depositar el trimestre en curso',
        ];
    }

    public function labelDepositoLiquidacion(): string
    {
        return self::opcionesDepositoLiquidacion()[$this->deposito_liquidacion_trimestre]
            ?? self::opcionesDepositoLiquidacion()[self::DEPOSITO_LIQUIDACION_COMPLETO];
    }

    public function setRifAttribute(string $value): void
    {
        $this->attributes['rif'] = RifVenezolanoRule::formatear($value);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
