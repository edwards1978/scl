<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use App\Rules\CedulaVenezolanaRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empleado extends Model
{
    use RegistraAuditoria;

    protected $fillable = [
        'patrono_id',
        'nombres',
        'apellidos',
        'cedula',
        'fecha_nacimiento',
        'genero',
        'telefono',
        'correo',
        'direccion',
        'fecha_ingreso',
        'cargo',
        'departamento',
        'tipo_contrato',
        'tipo_salario',
        'jornada_laboral',
        'salario_base',
        'salario_inicial',
        'moneda',
        'dias_descanso',
        'estatus',
        'capitaliza_intereses',
        'capitalizacion_autorizada_desde',
        'capitalizacion_documento',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'salario_base' => 'decimal:2',
            'salario_inicial' => 'decimal:2',
            'moneda' => 'string',
            'dias_descanso' => 'string',
            'estatus' => 'string',
            'capitaliza_intereses' => 'boolean',
            'capitalizacion_autorizada_desde' => 'date',
            'capitalizacion_documento' => 'string',
        ];
    }

    public function setCedulaAttribute(string $value): void
    {
        $this->attributes['cedula'] = CedulaVenezolanaRule::formatear($value);
    }

    public function patrono(): BelongsTo
    {
        return $this->belongsTo(Patrono::class);
    }

    public function vacaciones(): HasMany
    {
        return $this->hasMany(Vacacion::class)->orderByDesc('periodo_hasta');
    }

    public function cambiosSalarios(): HasMany
    {
        return $this->hasMany(CambioSalario::class)->orderByDesc('fecha_cambio');
    }

    public function sueldos(): HasMany
    {
        return $this->hasMany(Sueldo::class)->orderByDesc('periodo');
    }

    public function bonificacionesFinAno(): HasMany
    {
        return $this->hasMany(BonificacionFinAno::class);
    }

    /** Días de vacaciones ya disfrutados/pagados (acumulados históricos). */
    public function getDiasVacacionesTomadosAttribute(): int
    {
        return (int) $this->vacaciones()
            ->whereIn('estatus_vacacion', ['disfrutada', 'pagada'])
            ->sum('dias_vacaciones');
    }

    /**
     * Días de vacaciones devengados pero no aún disfrutados
     * (periodos por aprobar / aprobados).
     */
    public function getDiasVacacionesDevengadasAttribute(): int
    {
        return (int) $this->vacaciones()
            ->where('estatus_vacacion', 'pendiente')
            ->whereIn('estatus_calculo', ['por_aprobar', 'aprobada'])
            ->sum('dias_vacaciones');
    }

    /** Días de vacaciones disponibles para tomar. */
    public function getDiasVacacionesDisponiblesAttribute(): int
    {
        return max(0, $this->dias_vacaciones_devengadas - $this->dias_vacaciones_tomados);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    /** Indica si el trabajador está activo y pueden efectuarse nuevos cálculos. */
    public function estaActivo(): bool
    {
        return $this->estatus === 'Activo';
    }
}
