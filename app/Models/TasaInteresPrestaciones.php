<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use Illuminate\Database\Eloquent\Model;

class TasaInteresPrestaciones extends Model
{
    use RegistraAuditoria;

    protected $table = 'tasas_interes_prestaciones';

    protected $fillable = [
        'periodo',
        'tasa_interes',
        'fuente',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'string',
            'tasa_interes' => 'decimal:4',
            'fuente' => 'string',
        ];
    }

    public function labelFuente(): string
    {
        return $this->fuente === 'excel' ? 'Excel BCV' : 'Manual';
    }
}
