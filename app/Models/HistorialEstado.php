<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HistorialEstado extends Model
{
    protected $table = 'historial_estados';

    public const UPDATED_AT = null;

    protected $fillable = [
        'patrono_id',
        'auditable_type',
        'auditable_id',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'user_id',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patrono(): BelongsTo
    {
        return $this->belongsTo(Patrono::class);
    }

    public function labelCampo(): string
    {
        return match ($this->campo) {
            'estatus' => 'Estatus',
            'estatus_calculo' => 'Estatus del cálculo',
            'estatus_vacacion' => 'Estatus de la vacación',
            'estado' => 'Estado',
            'papelera' => 'Papelera',
            default => ucfirst(str_replace('_', ' ', $this->campo)),
        };
    }

    public function labelValor(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($valor) {
            'por_aprobar' => 'Por aprobar',
            'aprobada' => 'Aprobada',
            'aprobado' => 'Aprobado',
            'no_aprobada' => 'No aprobada',
            'anulada' => 'Anulada',
            'anulado' => 'Anulado',
            'calculado' => 'Calculado',
            'activo' => 'Activo',
            'pagado' => 'Pagado',
            'pendiente' => 'Pendiente',
            'pagada' => 'Pagada',
            'disfrutada' => 'Disfrutada',
            'eliminado' => 'Eliminado',
            'restaurado' => 'Restaurado',
            default => ucfirst(str_replace('_', ' ', $valor)),
        };
    }
}
