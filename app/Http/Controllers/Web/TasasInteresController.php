<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TasaInteresPrestaciones;
use App\Services\TasasInteresService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class TasasInteresController extends Controller
{
    public function __construct(
        private TasasInteresService $service = new TasasInteresService,
    ) {}

    /** Listado de tasas de interés registradas. */
    public function index(Request $request): View
    {
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $desde = $request->string('desde')->toString();
        $hasta = $request->string('hasta')->toString();
        $fuente = $request->string('fuente')->toString();

        $tasas = TasaInteresPrestaciones::query()
            ->when($desde, fn ($q) => $q->where('periodo', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('periodo', '<=', $hasta))
            ->when(in_array($fuente, ['excel', 'manual'], true), fn ($q) => $q->where('fuente', $fuente))
            ->orderByDesc('periodo')
            ->paginate($porPagina)
            ->withQueryString();

        return view('tasas-interes.index', compact('tasas', 'porPagina', 'desde', 'hasta', 'fuente'));
    }

    /** Registra una tasa manualmente (periodo YYYY-MM + tasa). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periodo' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'tasa_interes' => ['required', 'numeric', 'min:0'],
        ]);

        $this->service->guardarTasa(
            Carbon::createFromFormat('Y-m', $data['periodo']),
            (float) $data['tasa_interes'],
            'manual'
        );

        return redirect()->route('tasas-interes.index')
            ->with('success', "Tasa de interés del periodo {$data['periodo']} registrada/actualizada.");
    }

    /** Importa el archivo Excel del BCV (hoja principal). */
    public function importar(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'archivo' => ['required', 'file', 'mimes:xls,xlsx'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->with('error', 'Debe cargar un archivo Excel (.xls o .xlsx).');
        }

        try {
            $resultado = $this->service->importarExcel($request->file('archivo')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo procesar el archivo: '.$e->getMessage());
        }

        $errores = $resultado['errores'];

        $mensaje = "Archivo procesado: {$resultado['insertados']} tasa(s) importada(s) de {$resultado['procesados']} fila(s) leída(s).";
        if (count($errores) > 0) {
            $mensaje .= ' '.count($errores).' aviso(s): '.implode(' | ', array_slice($errores, 0, 5));

            return redirect()->route('tasas-interes.index')->with('info', $mensaje);
        }

        return redirect()->route('tasas-interes.index')->with('success', $mensaje);
    }

    /** Elimina una tasa registrada. */
    public function destroy(TasaInteresPrestaciones $tasa): RedirectResponse
    {
        $periodo = $tasa->periodo;
        $tasa->delete();

        return redirect()->route('tasas-interes.index')
            ->with('success', "Tasa de interés del periodo {$periodo} eliminada.");
    }
}
