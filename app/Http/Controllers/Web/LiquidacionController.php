<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\Patrono;
use App\Services\LiquidacionService;
use App\Services\PrestacionesSocialesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LiquidacionController extends Controller
{
    public function __construct(
        private LiquidacionService $service = new LiquidacionService,
        private PrestacionesSocialesService $prestaciones = new PrestacionesSocialesService,
    ) {}

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
        $liquidacion->load(['empleado', 'asignaciones', 'deducciones']);
        $empleado = $liquidacion->empleado;

        return view('liquidaciones.show', compact('liquidacion', 'empleado'));
    }

    /** Aprueba la liquidación y marca al trabajador como inactivo. */
    public function aprobar(int $id): RedirectResponse
    {
        $liquidacion = $this->liquidacionDePatrono($id);

        if ($liquidacion->estatus === 'aprobada') {
            return back()->with('info', 'La liquidación ya estaba aprobada.');
        }

        if ($liquidacion->estatus === 'anulada') {
            return back()->with('error', 'No se puede aprobar una liquidación anulada.');
        }

        DB::transaction(function () use ($liquidacion) {
            $liquidacion->update(['estatus' => 'aprobada']);
            $liquidacion->empleado->update(['estatus' => 'Inactivo']);

            // Con el egreso ya vigente, los períodos se recalculan con corte en
            // esa fecha para guardar la fracción del último trimestre.
            $this->prestaciones->regenerar($liquidacion->empleado);
        });

        return redirect()->route('liquidaciones.show', $liquidacion->id)
            ->with('success', 'Liquidación aprobada. El trabajador quedó inactivo.');
    }

    /** Anula la liquidación y reactiva al trabajador si no queda otra vigente. */
    public function anular(int $id): RedirectResponse
    {
        $liquidacion = $this->liquidacionDePatrono($id);

        if ($liquidacion->estatus === 'anulada') {
            return back()->with('info', 'La liquidación ya estaba anulada.');
        }

        DB::transaction(function () use ($liquidacion) {
            $liquidacion->update(['estatus' => 'anulada']);
            $this->reactivarSiCorresponde($liquidacion->empleado, $liquidacion->id);

            // Sin el egreso vigente se restablecen los períodos posteriores.
            $this->prestaciones->regenerar($liquidacion->empleado);
        });

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
            'conceptos' => ['required', 'array'],
            'conceptos.*.dias' => ['nullable', 'numeric', 'min:0'],
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
        $liquidacion->load(['empleado', 'asignaciones', 'deducciones', 'patrono']);
        $empleado = $liquidacion->empleado;

        return Pdf::loadView('liquidaciones.pdf', [
            'liquidacion' => $liquidacion,
            'empleado' => $empleado,
            'patrono' => $liquidacion->patrono,
            'asignaciones' => $liquidacion->asignaciones,
            'deducciones' => $liquidacion->deducciones,
            'total_asignaciones' => (float) $liquidacion->total_asignaciones,
            'total_deducciones' => (float) $liquidacion->total_deducciones,
            'fecha_emision' => Carbon::now(),
        ])->setPaper('letter', 'portrait')
            ->stream("liquidacion-{$empleado->nombre_completo}.pdf");
    }

    /** Elimina una liquidación pendiente; las aprobadas deben anularse. */
    public function destroy(int $id): RedirectResponse
    {
        $liquidacion = $this->liquidacionDePatrono($id);

        if ($liquidacion->estatus === 'aprobada') {
            return back()->with('error', 'No se puede eliminar una liquidación aprobada. Anúlela primero.');
        }

        $empleado = $liquidacion->empleado;
        DB::transaction(function () use ($liquidacion, $empleado) {
            $liquidacion->delete();
            $this->reactivarSiCorresponde($empleado);
            $this->prestaciones->regenerar($empleado);
        });

        return redirect()->route('liquidaciones.index')
            ->with('success', 'Liquidación eliminada.');
    }

    /**
     * Reactiva al trabajador solo si no le queda otra liquidación vigente.
     * Evita reactivarlo cuando existe otra liquidación por aprobar o aprobada.
     */
    private function reactivarSiCorresponde(Empleado $empleado, ?int $excluirId = null): void
    {
        $otraVigente = Liquidacion::where('empleado_id', $empleado->id)
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->whereIn('estatus', ['por_aprobar', 'aprobada'])
            ->exists();

        if (! $otraVigente && $empleado->estatus === 'Inactivo') {
            $empleado->update(['estatus' => 'Activo']);
        }
    }
}
