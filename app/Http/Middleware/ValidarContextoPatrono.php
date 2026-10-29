<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Patrono;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ValidarContextoPatrono
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $patronoId = session('patrono_id') ?? $this->restaurarDeCookie($request);

        if (! $patronoId) {
            return redirect()->route('entorno.seleccionar')
                ->with('error', 'Debe seleccionar un patrono antes de continuar.');
        }

        $patrono = Patrono::find((int) $patronoId);

        if (! $patrono || $patrono->estado !== 'Activo') {
            $this->olvidarContexto();

            return redirect()->route('entorno.seleccionar')
                ->with('error', 'El patrono seleccionado ya no está activo. Seleccione otro.');
        }

        // La cookie de contexto está cifrada y ligada al usuario: una sesión
        // manipulada o un patrono revocado no puede abrir datos de otro patrono.
        if (! Gate::forUser($user)->allows('view', $patrono)) {
            $this->olvidarContexto();

            abort(403, 'No tiene acceso al patrono seleccionado.');
        }

        session([
            'patrono_id' => $patrono->id,
            'nombre_patrono' => $patrono->razon_social,
        ]);

        return $next($request);
    }

    /** Recupera el patrono desde la cookie cifrada del contexto. */
    private function restaurarDeCookie(Request $request): ?int
    {
        $ctx = json_decode((string) $request->cookie('scl_ctx'), true);

        if (! is_array($ctx) || ! isset($ctx['patrono_id'])) {
            return null;
        }

        $patrono = Patrono::where('id', (int) $ctx['patrono_id'])
            ->where('estado', 'Activo')
            ->first();

        if (! $patrono || ! Gate::allows('view', $patrono)) {
            $this->limpiarCookie();

            return null;
        }

        return $patrono->id;
    }

    private function olvidarContexto(): void
    {
        session()->forget(['patrono_id', 'nombre_patrono']);
        $this->limpiarCookie();
    }

    private function limpiarCookie(): void
    {
        Cookie::queue(Cookie::forget('scl_ctx'));
    }
}
