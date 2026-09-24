<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Feriado;
use App\Models\FeriadoFijo;
use App\Services\DiasFeriadosService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class FeriadosController extends Controller
{
    public function __construct(
        private DiasFeriadosService $service = new DiasFeriadosService,
    ) {
    }

    public function index(): View
    {
        $fijos = FeriadoFijo::query()->orden()->get();
        $materializados = Feriado::orderByDesc('fecha')->limit(20)->get();

        return view('feriados.index', compact('fijos', 'materializados'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dia'         => ['required', 'integer', 'min:1', 'max:31'],
            'mes'         => ['required', 'integer', 'min:1', 'max:12'],
            'descripcion' => ['required', 'string', 'max:150'],
        ]);

        if (! checkdate((int) $data['mes'], (int) $data['dia'], 2024)) {
            return back()->withInput()->with('error', 'La fecha no es válida para ese mes.');
        }

        if (FeriadoFijo::where('mes', $data['mes'])->where('dia', $data['dia'])->exists()) {
            return back()->withInput()->with('error', 'Ya existe un feriado fijo en esa fecha.');
        }

        FeriadoFijo::create($data);

        $this->regenerarAnioActual();

        return redirect()->route('feriados.index')->with('success', 'Feriado fijo agregado.');
    }

    public function update(Request $request, FeriadoFijo $feriado): RedirectResponse
    {
        $data = $request->validate([
            'dia'         => ['required', 'integer', 'min:1', 'max:31'],
            'mes'         => ['required', 'integer', 'min:1', 'max:12'],
            'descripcion' => ['required', 'string', 'max:150'],
            'activo'      => ['nullable', 'boolean'],
        ]);

        if (! checkdate((int) $data['mes'], (int) $data['dia'], 2024)) {
            return back()->withInput()->with('error', 'La fecha no es válida para ese mes.');
        }

        $duplicado = FeriadoFijo::where('mes', $data['mes'])
            ->where('dia', $data['dia'])
            ->where('id', '!=', $feriado->id)
            ->exists();

        if ($duplicado) {
            return back()->withInput()->with('error', 'Ya existe otro feriado fijo en esa fecha.');
        }

        $feriado->update([
            'dia'         => $data['dia'],
            'mes'         => $data['mes'],
            'descripcion' => $data['descripcion'],
            'activo'      => (bool) ($data['activo'] ?? false),
        ]);

        $this->regenerarAnioActual();

        return redirect()->route('feriados.index')->with('success', 'Feriado fijo actualizado.');
    }

    public function destroy(FeriadoFijo $feriado): RedirectResponse
    {
        $feriado->delete();
        $this->regenerarAnioActual();

        return redirect()->route('feriados.index')->with('success', 'Feriado fijo eliminado.');
    }

    /** Regenera los feriados materializados del año vigente. */
    public function regenerar(): RedirectResponse
    {
        $this->regenerarAnioActual();

        return redirect()->route('feriados.index')->with('success', 'Calendario regenerado.');
    }

    private function regenerarAnioActual(): void
    {
        $this->service->materializarAnio((int) Carbon::now()->format('Y'));
    }
}