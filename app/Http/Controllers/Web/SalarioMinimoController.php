<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalarioMinimo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalarioMinimoController extends Controller
{
    public function index(): View
    {
        $salarios = SalarioMinimo::orderByDesc('fecha_vigencia')->get();

        return view('salarios-minimos.index', compact('salarios'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha_vigencia' => ['required', 'date'],
            'gaceta_oficial' => ['required', 'string', 'max:50'],
            'mensual_bs' => ['required', 'numeric', 'gt:0'],
            'diario_bs' => ['required', 'numeric', 'gte:0'],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        if (SalarioMinimo::where('fecha_vigencia', $data['fecha_vigencia'])->exists()) {
            return back()->withInput()->with('error', 'Ya existe un salario mínimo con esa fecha de vigencia.');
        }

        SalarioMinimo::create($data);

        return redirect()->route('salarios-minimos.index')->with('success', 'Salario mínimo guardado.');
    }

    public function update(Request $request, SalarioMinimo $salario): RedirectResponse
    {
        $data = $request->validate([
            'fecha_vigencia' => ['required', 'date'],
            'gaceta_oficial' => ['required', 'string', 'max:50'],
            'mensual_bs' => ['required', 'numeric', 'gt:0'],
            'diario_bs' => ['required', 'numeric', 'gte:0'],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        $duplicado = SalarioMinimo::where('fecha_vigencia', $data['fecha_vigencia'])
            ->where('id', '!=', $salario->id)
            ->exists();

        if ($duplicado) {
            return back()->withInput()->with('error', 'Ya existe otro salario mínimo con esa fecha de vigencia.');
        }

        $salario->update($data);

        return redirect()->route('salarios-minimos.index')->with('success', 'Salario mínimo actualizado.');
    }

    public function destroy(SalarioMinimo $salario): RedirectResponse
    {
        $salario->delete();

        return redirect()->route('salarios-minimos.index')->with('success', 'Salario mínimo eliminado.');
    }
}
