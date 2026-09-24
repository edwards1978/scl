<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patrono;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EntornoController extends Controller
{
    public function seleccionar(Request $request): View|RedirectResponse
    {
        $patronos = Patrono::where('estado', 'Activo')->orderBy('razon_social')->get();

        if ($patronos->isEmpty()) {
            return redirect()->route('patronos.create')
                ->with('info', 'Registre el primer patrono para comenzar.');
        }

        $patronoId = (int) ($request->get('patrono', session('patrono_id', $patronos->first()->id)));

        $patrono = $patronos->firstWhere('id', $patronoId);
        if ($patrono) {
            session()->put('patrono_id', $patrono->id);
            session()->put('nombre_patrono', $patrono->razon_social);
        }

        return view('entorno.seleccionar', compact('patronos', 'patronoId'));
    }

    public function guardar(Request $request): RedirectResponse
    {
        $request->validate([
            'patrono_id' => ['required', 'exists:patronos,id'],
        ]);

        $patrono = Patrono::where('id', (int) $request->input('patrono_id'))
            ->where('estado', 'Activo')
            ->firstOrFail();

        session([
            'patrono_id'    => $patrono->id,
            'nombre_patrono'=> $patrono->razon_social,
        ]);

        setcookie('scl_ctx', json_encode([
            'patrono_id'    => $patrono->id,
            'nombre_patrono'=> $patrono->razon_social,
        ]), time() + 86400 * 30, '/', '', false, true);

        return redirect()->route('empleados.index')
            ->with('success', "Contexto seleccionado: {$patrono->razon_social}.");
    }

    public function limpiar(): RedirectResponse
    {
        session()->forget(['patrono_id', 'nombre_patrono']);
        setcookie('scl_ctx', '', time() - 3600, '/');

        return redirect()->route('entorno.seleccionar');
    }
}
