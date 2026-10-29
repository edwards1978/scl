<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use Illuminate\Database\Eloquent\Model;

class TasaBcv extends Model
{
    use RegistraAuditoria;

    protected $table = 'tasas_bcv';

    protected $fillable = [
        'fecha',
        'tasa_usd',
        'fuente',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tasa_usd' => 'decimal:6',
        ];
    }

    public function __toString(): string
    {
        return (string) $this->tasa_usd;
    }
}
