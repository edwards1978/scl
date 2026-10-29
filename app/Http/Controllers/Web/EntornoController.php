<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patrono;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EntornoController extends Controller
{
    public function seleccionar(Request $request): View
    {
        $user = $request->user();
        $patronos = $user->patronosDisponibles();

        $patronoId = (int) $request->get('patrono', session('patrono_id', $patronos->first()?->id));
        $patrono = $patronos->firstWhere('id', $patronoId);

        if ($patrono) {
            session([
                'patrono_id' => $patrono->id,
                'nombre_patrono' => $patrono->razon_social,
            ]);
        }

        return view('entorno.seleccionar', [
            'patronos' => $patronos,
            'patronoId' => $patrono?->id,
            'puedeGestionarPatronos' => $user->esAdministrador(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $request->validate([
            'patrono_id' => ['required', 'exists:patronos,id'],
        ]);

        $patrono = Patrono::where('id', (int) $request->input('patrono_id'))
            ->where('estado', 'Activo')
            ->firstOrFail();

        Gate::authorize('view', $patrono);

        session([
            'patrono_id' => $patrono->id,
            'nombre_patrono' => $patrono->razon_social,
        ]);

        // Cookie cifrada y autenticada por EncryptCookies: no se puede alterar
        // el patrono sin la clave de la aplicación.
        Cookie::queue(Cookie::make('scl_ctx', json_encode([
            'patrono_id' => $patrono->id,
        ]), 60 * 24 * 30, '/', null, $request->isSecure(), true));

        return redirect()->route('empleados.index')
            ->with('success', "Contexto seleccionado: {$patrono->razon_social}.");
    }

    public function limpiar(): RedirectResponse
    {
        session()->forget(['patrono_id', 'nombre_patrono']);
        Cookie::queue(Cookie::forget('scl_ctx'));

        return redirect()->route('entorno.seleccionar');
    }
}
