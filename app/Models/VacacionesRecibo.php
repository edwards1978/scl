<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacacionesRecibo extends Model
{
    protected $table = 'vacaciones_recibos';

    protected $fillable = [
        'vacacion_id',
        'empleado_id',
        'fecha_emision',
        'fecha_inicio',
        'fecha_retiro',
        'asignaciones',
        'deducciones',
        'total_asignaciones',
        'total_deducciones',
        'neto',
        'moneda',
        'tipo_cambio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'        => 'date',
            'fecha_inicio'         => 'date',
            'fecha_retiro'         => 'date',
            'asignaciones'         => 'array',
            'deducciones'          => 'array',
            'total_asignaciones'   => 'decimal:2',
            'total_deducciones'    => 'decimal:2',
            'neto'                 => 'decimal:2',
            'tipo_cambio'          => 'decimal:4',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function vacacion(): BelongsTo
    {
        return $this->belongsTo(Vacacion::class);
    }
}
