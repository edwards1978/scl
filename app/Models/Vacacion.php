<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vacacion extends Model
{
    protected $table = 'vacaciones';

    protected $fillable = [
        'patrono_id',
        'empleado_id',
        'fecha_calculo',
        'fecha_inicio',
        'fecha_inicio_real',
        'fecha_fin_real',
        'fecha_reintegro',
        'nro_vacacion',
        'periodo_desde',
        'periodo_hasta',
        'dias_vacaciones',
        'dias_adicionales',
        'dias_bono',
        'dias_bono_adicionales',
        'dias_descanso',
        'dias_feriados',
        'feriados_descanso_en_periodo',
        'salario_normal',
        'moneda',
        'estatus_calculo',
        'estatus_vacacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_calculo' => 'date',
            'fecha_inicio' => 'date',
            'fecha_inicio_real' => 'date',
            'fecha_fin_real' => 'date',
            'fecha_reintegro' => 'date',
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'nro_vacacion' => 'integer',
            'dias_vacaciones' => 'integer',
            'dias_adicionales' => 'integer',
            'dias_bono' => 'integer',
            'dias_bono_adicionales' => 'integer',
            'dias_descanso' => 'integer',
            'dias_feriados' => 'integer',
            'feriados_descanso_en_periodo' => 'array',
            'salario_normal' => 'decimal:2',
            'moneda' => 'string',
            'estatus_calculo' => 'string',
            'estatus_vacacion' => 'string',
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

    public function recibos(): HasMany
    {
        return $this->hasMany(VacacionesRecibo::class, 'vacacion_id')->latest('fecha_emision');
    }

    public function recibo(): HasMany
    {
        return $this->recibos();
    }

    /** Días hábiles totales de disfrute (vacaciones + adicionales). */
    public function diasHabilesDisfrute(): int
    {
        return $this->dias_vacaciones + $this->dias_adicionales;
    }

    /** Días de salario del bono vacacional (15 + adicionales). */
    public function diasHabilesBono(): int
    {
        return $this->dias_bono + $this->dias_bono_adicionales;
    }

    public function labelEstatusCalculo(): string
    {
        return match ($this->estatus_calculo) {
            'aprobada' => 'Aprobada',
            'no_aprobada' => 'No aprobada',
            default => 'Por aprobar',
        };
    }

    public function labelEstatusVacacion(): string
    {
        return match ($this->estatus_vacacion) {
            'pagada' => 'Pagada / Disfrutada',
            'disfrutada' => 'Disfrutada',
            default => 'Pendiente',
        };
    }

    public function colorEstatusCalculo(): string
    {
        return match ($this->estatus_calculo) {
            'aprobada' => 'bg-green-50 text-green-700',
            'no_aprobada' => 'bg-red-50 text-red-700',
            default => 'bg-amber-50 text-amber-700',
        };
    }

    public function colorEstatusVacacion(): string
    {
        return match ($this->estatus_vacacion) {
            'pagada', 'disfrutada' => 'bg-blue-50 text-blue-700',
            default => 'bg-gray-100 text-gray-500',
        };
    }

    public function puedeAprobar(): bool
    {
        return in_array($this->estatus_calculo, ['por_aprobar', 'no_aprobada'], true);
    }

    public function puedeAnular(): bool
    {
        return $this->estatus_vacacion === 'pendiente'
            && in_array($this->estatus_calculo, ['por_aprobar', 'aprobada'], true);
    }

    public function puedePagar(): bool
    {
        return $this->estatus_calculo === 'aprobada'
            && ! in_array($this->estatus_vacacion, ['pagada', 'disfrutada'], true);
    }

    public function puedeBorrar(): bool
    {
        return $this->estatus_vacacion === 'pendiente'
            && $this->recibos()->doesntExist();
    }

    public function duracionDias(): int
    {
        if (! $this->fecha_inicio_real || ! $this->fecha_fin_real) {
            return 0;
        }

        return (int) ($this->fecha_inicio_real->diffInDays($this->fecha_fin_real) + 1);
    }
}
