<?php

declare(strict_types=1);
use App\Models\SalarioMinimo;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

if (! function_exists('formato_monto')) {
    function formato_monto(float $valor, int $decimales = 2): string
    {
        return number_format($valor, $decimales, ',', '.');
    }
}

if (! function_exists('parsear_numero')) {
    function parsear_numero(?string $valor): float
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 0.0;
        }

        // Coma presente => formato VE: punto es miles, coma es decimal.
        if (str_contains($valor, ',')) {
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        } elseif (substr_count($valor, '.') > 1) {
            // Sin coma y varios puntos => miles sin decimales ("9.999").
            $valor = str_replace('.', '', $valor);
        } elseif (substr_count($valor, '.') === 1) {
            // Un solo punto sin coma: si la parte decimal tiene 3 dígitos es
            // miles ("9.999"); si tiene 1-2 es decimal ("9999.99").
            [, $decimal] = explode('.', $valor);
            if (strlen($decimal) === 3) {
                $valor = str_replace('.', '', $valor);
            }
        }

        return (float) $valor;
    }
}

if (! function_exists('salario_minimo_en')) {
    /**
     * Salario mínimo vigente en o antes de una fecha (la fila más reciente).
     *
     * @return array{mensual_bs:float, diario_bs:float, gaceta:string, fecha_vigencia:?string}|null
     */
    function salario_minimo_en(?CarbonInterface $fecha = null): ?array
    {
        $vigente = SalarioMinimo::vigenteEn($fecha ?? Carbon::now());

        if (! $vigente) {
            return null;
        }

        return [
            'mensual_bs' => (float) $vigente->mensual_bs,
            'diario_bs' => (float) $vigente->diario_bs,
            'gaceta' => $vigente->gaceta_oficial,
            'fecha_vigencia' => $vigente->fecha_vigencia?->toDateString(),
        ];
    }
}

if (! function_exists('numero_a_letras')) {
    /**
     * Convierte un número entero a su representación en letras en español
     * (minúsculas y acentuadas). Soporta hasta millones.
     */
    function numero_a_letras(float|int $numero): string
    {
        $numero = (int) floor(abs($numero));

        if ($numero === 0) {
            return 'cero';
        }

        $unidades = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $especiales = [
            11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
            16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
            21 => 'veintiún', 22 => 'veintidós', 23 => 'veintitrés', 24 => 'veinticuatro',
            25 => 'veinticinco', 26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve',
        ];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $convertirTresDigitos = function (int $n) use ($unidades, $decenas, $especiales, $centenas) {
            if ($n === 0) {
                return '';
            }
            if ($n === 100) {
                return 'cien';
            }

            $c = (int) ($n / 100);
            $resto = $n % 100;

            $textoCentena = $centenas[$c];
            $textoResto = '';

            if ($resto > 0) {
                if ($resto < 10) {
                    $textoResto = $unidades[$resto];
                } elseif (isset($especiales[$resto])) {
                    $textoResto = $especiales[$resto];
                } else {
                    $d = (int) ($resto / 10);
                    $u = $resto % 10;
                    $textoResto = $decenas[$d].($u > 0 ? ' y '.$unidades[$u] : '');
                }
            }

            return trim($textoCentena.' '.$textoResto);
        };

        if ($numero < 1000) {
            return $convertirTresDigitos((int) $numero);
        }

        if ($numero < 1000000) {
            $miles = (int) ($numero / 1000);
            $resto = (int) $numero % 1000;
            $prefixMiles = ($miles === 1) ? 'mil' : $convertirTresDigitos($miles).' mil';

            return trim($prefixMiles.' '.$convertirTresDigitos($resto));
        }

        if ($numero < 1000000000) {
            $millones = (int) ($numero / 1000000);
            $resto = (int) $numero % 1000000;
            $prefixMillones = ($millones === 1) ? 'un millón' : numero_a_letras($millones).' millones';
            $textoResto = ($resto > 0) ? numero_a_letras($resto) : '';

            return trim($prefixMillones.' '.$textoResto);
        }

        return (string) $numero;
    }
}

if (! function_exists('monto_en_bolivares')) {
    /**
     * Escribe una cantidad en bolívares (VES) en letras.
     * Ej.: "ciento veinte mil bolívares con 30/100 céntimos." / "...bolívares exactos".
     */
    function monto_en_bolivares(float|int $monto): string
    {
        $partes = explode('.', number_format(abs($monto), 2, '.', ''));
        $entero = (int) $partes[0];
        $centimos = $partes[1];

        $letrasEntero = numero_a_letras($entero);
        $moneda = ($entero === 1) ? 'bolívar' : 'bolívares';
        $enteroTexto = ($entero === 1000000) ? 'un millón' : $letrasEntero;
        $deMoneda = ($entero >= 1000000) ? " de {$moneda}" : " {$moneda}";

        if ($centimos === '00') {
            $extracto = ($entero === 1) ? 'exacto' : 'exactos';

            return "{$enteroTexto}{$deMoneda} {$extracto}";
        }

        return "{$enteroTexto}{$deMoneda} con {$centimos}/100 céntimos.";
    }
}

if (! function_exists('monto_en_dolares')) {
    /**
     * Escribe una cantidad en dólares estadounidenses en minúsculas.
     * "cien veinte dólares estadounidenses exactos" / "... con cinco centavos".
     */
    function monto_en_dolares(float|int $monto): string
    {
        $partes = explode('.', number_format(abs($monto), 2, '.', ''));
        $entero = (int) $partes[0];
        $centavosNum = (int) $partes[1];

        $letrasEntero = numero_a_letras($entero);
        $moneda = ($entero === 1) ? 'dólar estadounidense' : 'dólares estadounidenses';
        $enteroTexto = ($entero === 1000000) ? 'un millón' : $letrasEntero;
        $deMoneda = ($entero >= 1000000) ? " de {$moneda}" : " {$moneda}";

        $textoEntero = "{$enteroTexto}{$deMoneda}";

        if ($centavosNum === 0) {
            return $textoEntero.' exactos';
        }

        $letrasCentavos = numero_a_letras($centavosNum);
        $textoCentavos = ($centavosNum === 1) ? 'centavo' : 'centavos';

        return "{$textoEntero} con {$letrasCentavos} {$textoCentavos}";
    }
}

if (! function_exists('menu_para_usuario')) {
    /**
     * Menú de navegación filtrado según el rol del usuario autenticado.
     *
     * @return array<string, array{titulo:string, opciones:array<int, array<string, mixed>>}>
     */
    function menu_para_usuario(?User $user = null): array
    {
        $user ??= auth()->user();
        $menu = config('menu', []);

        foreach ($menu as $clave => $seccion) {
            $opciones = array_values(array_filter(
                $seccion['opciones'] ?? [],
                fn (array $opcion): bool => ! ($opcion['solo_admin'] ?? false) || $user?->esAdministrador()
            ));

            if ($opciones === []) {
                unset($menu[$clave]);

                continue;
            }

            $menu[$clave]['opciones'] = $opciones;
        }

        return $menu;
    }
}

if (! function_exists('monto_en_letras')) {
    /**
     * Escribe una cantidad en letras según la moneda ("USD" o "BS").
     */
    function monto_en_letras(float $monto, string $moneda): string
    {
        return strtoupper($moneda) === 'USD'
            ? monto_en_dolares($monto)
            : monto_en_bolivares($monto);
    }
}
