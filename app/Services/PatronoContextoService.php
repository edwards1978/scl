<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Patrono;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class PatronoContextoService
{
    /**
     * Guarda el patrono activo en sesión y en la cookie cifrada de contexto.
     */
    public function seleccionar(Request $request, Patrono $patrono): void
    {
        session([
            'patrono_id' => $patrono->id,
            'nombre_patrono' => $patrono->razon_social,
        ]);

        // Cookie cifrada y autenticada por EncryptCookies: no se puede alterar
        // el patrono sin la clave de la aplicación.
        Cookie::queue(Cookie::make('scl_ctx', json_encode([
            'patrono_id' => $patrono->id,
        ]), 60 * 24 * 30, '/', null, $request->isSecure(), true));
    }

    /**
     * Elimina el patrono activo de sesión y cookie.
     */
    public function limpiar(): void
    {
        session()->forget(['patrono_id', 'nombre_patrono']);
        Cookie::queue(Cookie::forget('scl_ctx'));
    }
}
