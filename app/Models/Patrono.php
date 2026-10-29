<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use App\Rules\RifVenezolanoRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patrono extends Model
{
    use RegistraAuditoria;

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
            'anticipos_max_anio' => 'integer',
            'anticipos_pct_contabilidad' => 'integer',
            'anticipos_pct_fideicomiso' => 'integer',
            'anticipos_pct_fondo' => 'integer',
        ];
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
