<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatronoRequest;
use App\Http\Requests\UpdatePatronoRequest;
use App\Models\Patrono;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatronoController extends Controller
{
    public function index(Request $request): View
    {
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);
        $camposBusqueda = ['razon_social', 'rif', 'nombre_comercial'];
        $campo = in_array($request->get('campo'), $camposBusqueda) ? $request->get('campo') : 'razon_social';

        $query = Patrono::orderBy('razon_social');
        if ($buscar = $request->get('buscar')) {
            $query->where($campo, 'like', "%{$buscar}%");
        }

        $patronos = $query->withCount('empleados')->paginate($porPagina);

        return view('patronos.index', compact('patronos', 'camposBusqueda', 'campo'));
    }

    public function create(): View
    {
        return view('patronos.create');
    }

    public function store(StorePatronoRequest $request): RedirectResponse
    {
        $patrono = Patrono::create($request->validated());

        return redirect()->route('patronos.index')
            ->with('success', "Patrono {$patrono->razon_social} creado exitosamente.");
    }

    public function edit(Patrono $patrono): View
    {
        return view('patronos.edit', compact('patrono'));
    }

    public function update(UpdatePatronoRequest $request, Patrono $patrono): RedirectResponse
    {
        $patrono->update($request->validated());

        return redirect()->route('patronos.index')
            ->with('success', "Patrono {$patrono->razon_social} actualizado exitosamente.");
    }

    public function toggleEstado(Patrono $patrono): RedirectResponse
    {
        $patrono->update([
            'estado' => $patrono->estado === 'Activo' ? 'Inactivo' : 'Activo',
        ]);

        return redirect()->route('patronos.index')
            ->with('success', 'Estado cambiado exitosamente.');
    }

    public function destroy(Patrono $patrono): RedirectResponse
    {
        $razon = $patrono->razon_social;
        $patrono->delete();

        return redirect()->route('patronos.index')
            ->with('success', "Patrono {$razon} eliminado exitosamente.");
    }
}
