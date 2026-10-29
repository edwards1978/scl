<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CedulaVenezolanaRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = strtoupper(trim((string) $value));

        if (! preg_match('/^[VE]-?\d{6,8}$/', $value)) {
            $fail('La Cédula debe tener formato válido: V-12345678 o E-12345678.');
        }
    }

    public static function formatear(string $cedula): string
    {
        $cedula = strtoupper(trim($cedula));
        $cedula = preg_replace('/[^VE\d]/i', '', $cedula);
        if (strlen($cedula) >= 7 && strlen($cedula) <= 9) {
            $letra = substr($cedula, 0, 1);
            $numeros = substr($cedula, 1);

            return $letra.'-'.$numeros;
        }

        return $cedula;
    }
}
