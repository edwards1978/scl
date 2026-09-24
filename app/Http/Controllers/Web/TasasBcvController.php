<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TasaBcv;
use App\Services\SueldosService;
use App\Services\TasasBcvService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class TasasBcvController extends Controller
{
    public function __construct(
        private TasasBcvService $service = new TasasBcvService,
        private SueldosService $sueldos = new SueldosService,
    ) {
    }

    /** Listado de tasas registradas. */
    public function index(Request $request): View
    {
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $desde = $request->date('desde');
        $hasta = $request->date('hasta');
        $fuente = $request->string('fuente')->toString();

        $tasas = TasaBcv::query()
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde->toDateString()))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta->toDateString()))
            ->when(in_array($fuente, ['excel', 'manual'], true), fn ($q) => $q->where('fuente', $fuente))
            ->orderByDesc('fecha')
            ->paginate($porPagina)
            ->withQueryString();

        return view('tasas.index', compact('tasas', 'porPagina', 'desde', 'hasta', 'fuente'));
    }

    /** Registra una tasa manualmente. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha'    => ['required', 'date'],
            'tasa_usd' => ['required', 'numeric', 'min:0.000001'],
        ]);

        $this->service->guardarTasa(Carbon::parse($data['fecha']), (float) $data['tasa_usd'], 'manual');

        return redirect()->route('tasas.index')
            ->with('success', 'Tasa del BCV registrada/actualizada.');
    }

    /**
     * Importa el archivo Excel del BCV y procesa las tasas por lotes.
     */
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

        $mensaje = "Archivo procesado: {$resultado['insertadas']} tasa(s) importada(s) de {$resultado['procesadas']} hoja(s).";
        if (count($errores) > 0) {
            $mensaje .= ' '.count($errores).' hoja(s) omitida(s): '.implode(' | ', array_slice($errores, 0, 5));

            return redirect()->route('tasas.index')->with('info', $mensaje);
        }

        return redirect()->route('tasas.index')->with('success', $mensaje);
    }

    /** Elimina una tasa registrada. */
    public function destroy(TasaBcv $tasa): RedirectResponse
    {
        $tasa->delete();

        return redirect()->route('tasas.index')->with('success', 'Tasa eliminada.');
    }

    /** Verifica de forma general las tasas requeridas para calcular sueldos. */
    public function verificar(Request $request): View
    {
        $resultado = $this->sueldos->verificarTasasFaltantes();

        return view('tasas.verificar', compact('resultado'));
    }
}