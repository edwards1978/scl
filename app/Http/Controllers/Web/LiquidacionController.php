<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Liquidacion;
use App\Models\Patrono;
use App\Services\LiquidacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiquidacionController extends Controller
{
    public function __construct(
        private LiquidacionService $service = new LiquidacionService,
    ) {
    }

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    private function liquidacionDePatrono(int $id): Liquidacion
    {
        $liquidacion = Liquidacion::with('empleado')->findOrFail($id);

        abort_if((int) $liquidacion->patrono_id !== (int) session('patrono_id'), 404);

        return $liquidacion;
    }

    /** Listado de liquidaciones del patrono en contexto. */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $query = Liquidacion::with('empleado')
            ->where('patrono_id', $patrono->id)
            ->orderByDesc('fecha_liquidacion')
            ->orderByDesc('id');

        if ($empleadoId = $request->filled('empleado_id') ? (int) $request->get('empleado_id') : null) {
            $query->where('empleado_id', $empleadoId);
        }

        if (in_array($request->get('estatus'), ['por_aprobar', 'aprobada', 'anulada'], true)) {
            $query->where('estatus', $request->get('estatus'));
        }

        $liquidaciones = $query->paginate($porPagina)->withQueryString();

        $empleados = $patrono->empleados()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        return view('liquidaciones.index', compact(
            'liquidaciones', 'empleados', 'patrono', 'porPagina'
        ));
    }

    /** Pantalla para crear una liquidación (Livewire full-page). */
    public function create(): View
    {
        $patrono = $this->patronoActual();

        return view('liquidaciones.create', compact('patrono'));
    }

    /** Detalle de una liquidación con sus conceptos. */
    public function show(int $id): View
    {
        $liquidacion = $this->liquidacionDePatrono($id);
        $this->repararSiEsNecesario($liquidacion);
        $liquidacion->load(['empleado', 'asignaciones', 'deducciones']);
        $empleado = $liquidacion->empleado;

        return view('liquidaciones.show', compact('liquidacion', 'empleado'));
    }

    /** Aprueba la liquidación. */
    public function aprobar(int $id): RedirectResponse
    {
        $liquidacion = $this->liquidacionDePatrono($id);

        if ($liquidacion->estatus === 'aprobada') {
            return back()->with('info', 'La liquidación ya estaba aprobada.');
        }

        $liquidacion->update(['estatus' => 'aprobada']);

        return redirect()->route('liquidaciones.show', $liquidacion->id)
            ->with('success', 'Liquidación aprobada.');
    }

    /** Anula la liquidación. */
    public function anular(int $id): RedirectResponse
    {
        $liquidacion = $this->liquidacionDePatrono($id);

        if ($liquidacion->estatus === 'anulada') {
            return back()->with('info', 'La liquidación ya estaba anulada.');
        }

        $liquidacion->update(['estatus' => 'anulada']);

        return redirect()->route('liquidaciones.show', $liquidacion->id)
            ->with('success', 'Liquidación anulada.');
    }

    /** Edita importes/días de los conceptos y recalcula totales. */
    public function actualizarConceptos(Request $request, int $id): RedirectResponse
    {
        $liquidacion = $this->liquidacionDePatrono($id);

        if ($liquidacion->estatus === 'aprobada') {
            return back()->with('error', 'No se puede editar una liquidación aprobada.');
        }

        $conceptosData = $request->input('conceptos', []);
        foreach ($conceptosData as $cId => &$dato) {
            if (isset($dato['dias']) && $dato['dias'] === '') {
                $dato['dias'] = null;
            }
        }
        unset($dato);
        $request->merge(['conceptos' => $conceptosData]);

        $conceptos = $request->validate([
            'conceptos'   => ['required', 'array'],
            'conceptos.*.dias'    => ['nullable', 'numeric', 'min:0'],
            'conceptos.*.importe' => ['required', 'numeric', 'min:0'],
        ])['conceptos'];

        $this->service->actualizarConceptos($liquidacion, $conceptos);

        return redirect()->route('liquidaciones.show', $liquidacion->id)
            ->with('success', 'Conceptos actualizados correctamente.');
    }

    /** PDF de la liquidación. */
    public function pdf(int $id)
    {
        $liquidacion = $this->liquidacionDePatrono($id);
        $this->repararSiEsNecesario($liquidacion);
        $liquidacion->load(['empleado', 'asignaciones', 'deducciones', 'patrono']);
        $empleado = $liquidacion->empleado;

        return Pdf::loadView('liquidaciones.pdf', [
            'liquidacion' => $liquidacion,
            'empleado'    => $empleado,
            'patrono'     => $liquidacion->patrono,
            'asignaciones'=> $liquidacion->asignaciones,
            'deducciones' => $liquidacion->deducciones,
            'total_asignaciones' => (float) $liquidacion->total_asignaciones,
            'total_deducciones'  => (float) $liquidacion->total_deducciones,
            'fecha_emision'      => Carbon::now(),
        ])->setPaper('letter', 'portrait')
          ->stream("liquidacion-{$empleado->nombre_completo}.pdf");
    }

    /** Elimina una liquidación y restablece al trabajador a Activo. */
    public function destroy(int $id): RedirectResponse
    {
        $liquidacion = $this->liquidacionDePatrono($id);
        $empleado = $liquidacion->empleado;
        $liquidacion->delete();

        // Restablece el estatus del trabajador para permitir nuevos cálculos.
        $empleado->update(['estatus' => 'Activo']);

        return redirect()->route('liquidaciones.index')
            ->with('success', 'Liquidación eliminada y trabajador restablecido a Activo.');
    }

    /**
     * Regenera los conceptos de liquidaciones creadas antes de que existieran
     * los campos salario_diario / tipo_salario (para que el recibo muestre el
     * sueldo diario por renglón). Se aplica a cualquier estatus cuando los
     * conceptos de asignación carecen de esos datos.
     */
    private function repararSiEsNecesario(Liquidacion $liquidacion): void
    {
        $faltante = $liquidacion->conceptos()
            ->where('tipo', 'asignacion')
            ->whereIn('concepto', [
                'Prestaciones sociales',
                'Vacaciones fraccionadas',
                'Bono vacacional fraccionado',
                'Utilidades fraccionadas',
            ])
            ->whereNull('salario_diario')
            ->exists();

        $indemnizacionMalTipo = $liquidacion->conceptos()
            ->where('tipo', 'asignacion')
            ->where('concepto', 'Indemnización por terminación')
            ->whereNotNull('tipo_salario')
            ->exists();

        if ($faltante || $indemnizacionMalTipo) {
            $this->service->recalcular($liquidacion);
        }
    }
}
