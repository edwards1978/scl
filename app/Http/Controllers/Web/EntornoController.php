<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patrono;
use App\Services\PatronoContextoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EntornoController extends Controller
{
    public function __construct(private readonly PatronoContextoService $contexto) {}

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

        $this->contexto->seleccionar($request, $patrono);

        return redirect()->route('empleados.index')
            ->with('success', "Contexto seleccionado: {$patrono->razon_social}.");
    }

    public function limpiar(): RedirectResponse
    {
        $this->contexto->limpiar();

        return redirect()->route('entorno.seleccionar');
    }
}
