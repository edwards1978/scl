<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liquidacion extends Model
{
    protected $table = 'liquidaciones';

    protected $fillable = [
        'patrono_id',
        'empleado_id',
        'fecha_liquidacion',
        'fecha_ingreso',
        'fecha_egreso',
        'motivo',
        'observacion',
        'anios_servicio',
        'meses_fraccion',
        'dias_prestaciones',
        'dias_indemnizacion',
        'garantia_ab',
        'literal_c',
        'prestaciones_mayor',
        'prestaciones_origen',
        'prestaciones_diferencia',
        'salario_diario_normal',
        'salario_integral_diario',
        'moneda',
        'tasa_cambio',
        'total_asignaciones',
        'total_deducciones',
        'neto',
        'estatus',
    ];

    public const MOTIVOS = [
        'despido_justificado',
        'despido_no_justificado',
        'retiro_voluntario',
        'retiro_justificado',
        'voluntad_comun',
        'causa_ajena',
        'conclusion_contrato',
    ];

    protected function casts(): array
    {
        return [
            'fecha_liquidacion'        => 'date',
            'fecha_ingreso'            => 'date',
            'fecha_egreso'             => 'date',
            'anios_servicio'           => 'integer',
            'meses_fraccion'           => 'integer',
            'dias_prestaciones'        => 'integer',
            'dias_indemnizacion'       => 'integer',
            'garantia_ab'              => 'decimal:2',
            'literal_c'                => 'decimal:2',
            'prestaciones_mayor'       => 'decimal:2',
            'prestaciones_origen'      => 'string',
            'prestaciones_diferencia'  => 'decimal:2',
            'salario_diario_normal'    => 'decimal:2',
            'salario_integral_diario'  => 'decimal:2',
            'moneda'                   => 'string',
            'tasa_cambio'              => 'decimal:4',
            'total_asignaciones'       => 'decimal:2',
            'total_deducciones'        => 'decimal:2',
            'neto'                     => 'decimal:2',
            'estatus'                  => 'string',
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

    public function conceptos(): HasMany
    {
        return $this->hasMany(LiquidacionConcepto::class)->orderBy('tipo')->orderBy('orden');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(LiquidacionConcepto::class)
            ->where('tipo', 'asignacion')
            ->orderBy('orden');
    }

    public function deducciones(): HasMany
    {
        return $this->hasMany(LiquidacionConcepto::class)
            ->where('tipo', 'deduccion')
            ->orderBy('orden');
    }

    public function labelMotivo(): string
    {
        return match ($this->motivo) {
            'despido_justificado'  => 'Despido justificado',
            'despido_no_justificado' => 'Despido no justificado convenido',
            'retiro_voluntario'    => 'Retiro voluntario',
            'retiro_justificado'   => 'Retiro justificado',
            'voluntad_comun'       => 'Voluntad común de las partes',
            'causa_ajena'          => 'Causa ajena a la voluntad de las partes',
            'conclusion_contrato'  => 'Conclusión de contrato',
            default                => 'Otro',
        };
    }

    public function labelEstatus(): string
    {
        return match ($this->estatus) {
            'aprobada' => 'Aprobada',
            'anulada'  => 'Anulada',
            default    => 'Por aprobar',
        };
    }

    public function colorEstatus(): string
    {
        return match ($this->estatus) {
            'aprobada' => 'bg-green-50 text-green-700',
            'anulada'  => 'bg-red-50 text-red-700',
            default    => 'bg-amber-50 text-amber-700',
        };
    }

    public function esDobleIndemnizacion(): bool
    {
        return $this->dias_indemnizacion > 0;
    }
}