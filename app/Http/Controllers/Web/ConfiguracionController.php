<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateConfiguracionRequest;
use App\Models\Patrono;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    /** Pantalla de configuración del patrono en contexto. */
    public function index(): View
    {
        $patrono = $this->patronoActual();

        return view('configuracion.edit', compact('patrono'));
    }

    /** Guarda la configuración del patrono en contexto. */
    public function update(UpdateConfiguracionRequest $request): RedirectResponse
    {
        $patrono = $this->patronoActual();
        $patrono->update($request->validated());

        return redirect()->route('configuracion.index')
            ->with('success', "Configuración de {$patrono->razon_social} guardada correctamente.");
    }
}
