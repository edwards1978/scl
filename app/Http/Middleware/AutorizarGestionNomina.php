<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea las operaciones de escritura de la nómina para el rol Consulta.
 * Las lecturas (GET/HEAD) siguen permitidas y se filtran por contexto.
 */
class AutorizarGestionNomina
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if (! $request->user()?->puedeGestionar()) {
            abort(403, 'Su rol es de solo consulta: no puede modificar la nómina.');
        }

        return $next($request);
    }
}
