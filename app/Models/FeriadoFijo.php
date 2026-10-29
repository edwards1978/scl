<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Feriado fijo de calendario (día y mes, sin año concreto). Es editable por el
 * usuario (incluye feriados regionales o locales). Los feriados variables
 * (Carnaval y Semana Santa) no viven aquí: los calcula DiasFeriadosService.
 */
class FeriadoFijo extends Model
{
    use RegistraAuditoria;

    protected $table = 'feriados_fijos';

    protected $fillable = ['mes', 'dia', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'dia' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeOrden(Builder $query): Builder
    {
        return $query->orderBy('mes')->orderBy('dia');
    }
}
