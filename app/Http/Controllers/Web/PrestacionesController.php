<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use App\Services\PrestacionesSocialesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrestacionesController extends Controller
{
    public function __construct(
        private PrestacionesSocialesService $service = new PrestacionesSocialesService,
    ) {
    }

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    private function empleadoDePatrono(int $id): Empleado
    {
        $empleado = $this->patronoActual()->empleados()->with('patrono')->findOrFail($id);

        return $empleado;
    }

    /** Empleado activo (bloquea nuevos cálculos si está inactivo/liquidado). */
    private function empleadoActivo(int $id): Empleado
    {
        $empleado = $this->empleadoDePatrono($id);

        abort_if(! $empleado->estaActivo(), 409, 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.');

        return $empleado;
    }

    /** Listado de empleados del patrono con su saldo de prestaciones vigente. */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $empleados = $patrono->empleados()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate($porPagina)
            ->withQueryString();

        $saldos = [];
        foreach ($empleados as $empleado) {
            if (! $empleado->estaActivo()) {
                $saldos[$empleado->id] = 0.0;
                continue;
            }
            $this->service->asegurarPeriodos($empleado);
            $saldos[$empleado->id] = $this->service->saldoEn($empleado);
        }

        return view('prestaciones.index', compact('patrono', 'empleados', 'saldos', 'porPagina'));
    }

    /** Estado de cuenta mensual de un empleado. */
    public function show(Request $request, int $empleadoId): View
    {
        $empleado = $this->empleadoActivo($empleadoId);

        $cuenta = $this->service->estadoDeCuenta($empleado);

        $saldoActual = $cuenta->last()->saldo_final ?? 0.0;
        $totalDepositos = (float) $cuenta->sum('deposito_trimestre');
        $totalAdicionales = (float) $cuenta->sum('dias_adicionales');
        $totalIntereses = (float) $cuenta->sum('interes_mes');
        $totalAnticipos = (float) $cuenta->sum('anticipos');

        return view('prestaciones.show', compact(
            'empleado', 'cuenta', 'saldoActual',
            'totalDepositos', 'totalAdicionales', 'totalIntereses', 'totalAnticipos'
        ));
    }

    /** Recalcula el estado de cuenta completo de un empleado. */
    public function recalcular(Request $request, int $empleadoId): RedirectResponse
    {
        $empleado = $this->empleadoActivo($empleadoId);

        $creados = $this->service->regenerar($empleado);

        return redirect()->route('prestaciones.show', $empleado->id)
            ->with('success', "Estado de cuenta de {$empleado->nombre_completo} recalculado ({$creados} periodo(s)).");
    }

    /** PDF del estado de cuenta. */
    public function pdf(Request $request, int $empleadoId)
    {
        $empleado = $this->empleadoActivo($empleadoId);
        $patrono = $this->patronoActual();

        $cuenta = $this->service->estadoDeCuenta($empleado);

        $saldoActual = $cuenta->last()->saldo_final ?? 0.0;
        $totalDepositos = (float) $cuenta->sum('deposito_trimestre');
        $totalAdicionales = (float) $cuenta->sum('dias_adicionales');
        $totalIntereses = (float) $cuenta->sum('interes_mes');
        $totalAnticipos = (float) $cuenta->sum('anticipos');

        return Pdf::loadView('prestaciones.pdf', [
            'empleado'       => $empleado,
            'patrono'        => $patrono,
            'cuenta'         => $cuenta,
            'saldoActual'    => $saldoActual,
            'totalDepositos' => $totalDepositos,
            'totalAdicionales' => $totalAdicionales,
            'totalIntereses' => $totalIntereses,
            'totalAnticipos' => $totalAnticipos,
        ])->setPaper('letter', 'landscape')
          ->stream("estado-cuenta-prestaciones-{$empleado->nombre_completo}.pdf");
    }
}
