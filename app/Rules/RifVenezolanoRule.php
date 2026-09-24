<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RifVenezolanoRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = strtoupper(trim((string) $value));

        if (!preg_match('/^[VEPJGC]-?\d{8}-?\d$/', $value)) {
            $fail('El RIF debe tener formato válido: J-12345678-9 (V, E, J, P, G o C seguido de 8 dígitos y 1 dígito verificador).');
        }
    }

    public static function formatear(string $rif): string
    {
        $rif = strtoupper(trim($rif));
        $rif = preg_replace('/[^VEPJGC\d]/i', '', $rif);
        if (strlen($rif) === 10) {
            return substr($rif, 0, 1) . '-' . substr($rif, 1, 8) . '-' . substr($rif, 9, 1);
        }
        return $rif;
    }
}
