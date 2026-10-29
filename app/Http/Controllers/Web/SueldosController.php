<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\Sueldo;
use App\Services\SueldosService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SueldosController extends Controller
{
    public function __construct(
        private SueldosService $service = new SueldosService,
    ) {}

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    private function empleadoDePatrono(?int $id): ?Empleado
    {
        if (! $id) {
            return null;
        }

        return $this->patronoActual()->empleados()->find($id);
    }

    /**
     * Módulo de sueldos: no muestra nada hasta seleccionar un trabajador.
     * Al seleccionarlo asegura los sueldos pendientes (cálculo a mes cerrado).
     */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();

        $empleados = $patrono->empleados()
            ->where('estatus', 'Activo')
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        $empleado = $this->empleadoDePatrono($request->integer('empleado_id') ?: null);

        $sueldos = collect();
        $info = null;

        if ($empleado) {
            if ($empleado->estaActivo()) {
                $resultado = $this->service->asegurarSueldos($empleado);

                if ($resultado['creados'] > 0) {
                    $info = "Calculando sueldos… Se registraron {$resultado['creados']} periodo(s) al mes cerrado.";
                }
            } else {
                $info = 'Trabajador inactivo (liquidado): no se generan nuevos periodos de sueldo.';
            }

            $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

            $sueldos = Sueldo::where('empleado_id', $empleado->id)
                ->orderByDesc('periodo')
                ->paginate($porPagina)
                ->withQueryString();
        } else {
            $porPagina = 15;
        }

        return view('sueldos.index', compact(
            'patrono', 'empleados', 'empleado', 'sueldos', 'info', 'porPagina'
        ));
    }

    /**
     * Regenera los sueldos del trabajador seleccionado (o de todos los del
     * patrono si no se indica uno). Devuelve al listado de sueldos.
     */
    public function regenerar(Request $request): RedirectResponse
    {
        $patrono = $this->patronoActual();

        $empleado = $this->empleadoDePatrono($request->integer('empleado_id') ?: null);

        if ($empleado) {
            abort_if(! $empleado->estaActivo(), 409, 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.');

            $creados = $this->service->regenerarSueldos($empleado);
            $mensaje = "Sueldos de {$empleado->nombre_completo} recalculados ({$creados} periodos).";

            return redirect()->route('sueldos.index', ['empleado_id' => $empleado->id])
                ->with('success', $mensaje);
        }

        // Recalcular todos los trabajadores activos del patrono.
        $total = 0;
        foreach ($patrono->empleados()->where('estatus', 'Activo')->get() as $e) {
            $total += $this->service->regenerarSueldos($e);
        }

        return redirect()->route('sueldos.index')
            ->with('success', "Sueldos de todos los trabajadores recalculados ({$total} periodos).");
    }

    /**
     * Pantalla de variaciones salariales (cambios de sueldo) del trabajador.
     */
    public function variaciones(Request $request): View
    {
        $patrono = $this->patronoActual();

        $empleados = $patrono->empleados()
            ->where('estatus', 'Activo')
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        $empleado = $this->empleadoDePatrono($request->integer('empleado_id') ?: null);

        $cambios = collect();
        if ($empleado) {
            $cambios = CambioSalario::where('empleado_id', $empleado->id)
                ->orderByDesc('fecha_cambio')
                ->get();
        }

        return view('sueldos.variaciones', compact('patrono', 'empleados', 'empleado', 'cambios'));
    }

    /** Registra un cambio salarial y regenera desde la fecha del cambio. */
    public function storeVariacion(Request $request): RedirectResponse
    {
        $patrono = $this->patronoActual();

        $data = $request->validate([
            'empleado_id' => ['required', 'integer', "exists:empleados,id,patrono_id,{$patrono->id}"],
            'fecha_cambio' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'moneda' => ['required', 'in:USD,BS'],
        ]);

        $empleado = Empleado::findOrFail((int) $data['empleado_id']);

        $duplicado = CambioSalario::where('empleado_id', $empleado->id)
            ->where('fecha_cambio', $data['fecha_cambio'])
            ->exists();

        if ($duplicado) {
            return back()->withInput()
                ->with('error', 'Ya existe un cambio salarial para esa fecha.');
        }

        CambioSalario::create($data);

        // La ficha del trabajador refleja el sueldo vigente más reciente.
        $this->service->sincronizarFicha($empleado);
        // Regenera los sueldos desde el mes del cambio en adelante.
        $this->service->regenerarSueldos($empleado, Carbon::parse($data['fecha_cambio'])->format('Y-m'));

        return redirect()->route('sueldos.variaciones', ['empleado_id' => $empleado->id])
            ->with('success', 'Cambio salarial registrado y sueldos regenerados.');
    }

    /** Elimina un cambio salarial y regenera desde esa fecha. */
    public function destroyVariacion(CambioSalario $cambio): RedirectResponse
    {
        abort_if((int) $cambio->empleado->patrono_id !== (int) session('patrono_id'), 404);

        $empleado = $cambio->empleado;
        $desde = $cambio->fecha_cambio;

        $cambio->delete();

        $this->service->sincronizarFicha($empleado);
        $this->service->regenerarSueldos($empleado, $desde->format('Y-m'));

        return redirect()->route('sueldos.variaciones', ['empleado_id' => $empleado->id])
            ->with('success', 'Cambio salarial eliminado y sueldos regenerados.');
    }
}
