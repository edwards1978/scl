<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Patrono;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidarContextoPatrono
{
    public function handle(Request $request, Closure $next): Response
    {
        $patronoId = session('patrono_id');

        if (! $patronoId) {
            $this->restaurarDeCookie();
            $patronoId = session('patrono_id');
        }

        if (! $patronoId) {
            return redirect()->route('entorno.seleccionar')
                ->with('error', 'Debe seleccionar un patrono antes de continuar.');
        }

        $patrono = Patrono::find((int) $patronoId);
        if (! $patrono || $patrono->estado !== 'Activo') {
            session()->forget(['patrono_id', 'nombre_patrono']);
            $this->limpiarCookie();

            return redirect()->route('entorno.seleccionar')
                ->with('error', 'El patrono seleccionado ya no está activo. Seleccione otro.');
        }

        return $next($request);
    }

    private function restaurarDeCookie(): void
    {
        if (! isset($_COOKIE['scl_ctx'])) {
            return;
        }

        $ctx = json_decode($_COOKIE['scl_ctx'], true);
        if (! is_array($ctx) || ! isset($ctx['patrono_id'])) {
            return;
        }

        $patrono = Patrono::where('id', (int) $ctx['patrono_id'])
            ->where('estado', 'Activo')
            ->first();

        if (! $patrono) {
            $this->limpiarCookie();
            return;
        }

        session([
            'patrono_id'    => $patrono->id,
            'nombre_patrono'=> $patrono->razon_social,
        ]);
    }

    private function limpiarCookie(): void
    {
        setcookie('scl_ctx', '', time() - 3600, '/');
    }
}
