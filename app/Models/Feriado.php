<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $fillable = ['fecha', 'descripcion', 'calculado'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'calculado' => 'boolean',
        ];
    }
}
