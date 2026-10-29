<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    protected $fillable = ['clave', 'valor', 'tipo'];

    public static function get(string $clave, mixed $default = null): mixed
    {
        $parametro = static::where('clave', $clave)->first();

        if (! $parametro) {
            return $default;
        }

        return match ($parametro->tipo) {
            'integer' => (int) $parametro->valor,
            'float' => (float) $parametro->valor,
            'boolean' => $parametro->valor === '1' || $parametro->valor === 'true',
            'json' => json_decode($parametro->valor, true),
            default => $parametro->valor,
        };
    }

    public static function set(string $clave, mixed $valor, string $tipo = 'string'): void
    {
        if ($tipo === 'json') {
            $valorStr = json_encode($valor, JSON_UNESCAPED_UNICODE);
        } elseif ($tipo === 'boolean') {
            $valorStr = $valor ? '1' : '0';
        } else {
            $valorStr = (string) $valor;
        }

        static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valorStr, 'tipo' => $tipo],
        );
    }
}
