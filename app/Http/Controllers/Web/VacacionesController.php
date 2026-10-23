<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\Vacacion;
use App\Services\VacacionesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VacacionesController extends Controller
{
    public function __construct(
        private VacacionesService $service = new VacacionesService,
    ) {
    }

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    /** Listado global de vacaciones del patrono en contexto. */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $query = Vacacion::with('empleado')
            ->where('patrono_id', $patrono->id)
            ->orderByDesc('fecha_calculo')
            ->orderByDesc('id');

        if ($empleadoId = $request->filled('empleado_id') ? (int) $request->get('empleado_id') : null) {
            $query->where('empleado_id', $empleadoId);
        }

        if ($ano = $request->filled('ano') ? (int) $request->get('ano') : null) {
            $query->whereYear('periodo_hasta', $ano);
        }

        if (in_array($request->get('estatus_calculo'), ['por_aprobar', 'aprobada', 'no_aprobada'], true)) {
            $query->where('estatus_calculo', $request->get('estatus_calculo'));
        }

        if (in_array($request->get('estatus_vacacion'), ['pendiente', 'pagada', 'disfrutada'], true)) {
            $query->where('estatus_vacacion', $request->get('estatus_vacacion'));
        }

        $vacaciones = $query->paginate($porPagina)->withQueryString();

        $empleados = $patrono->empleados()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        return view('vacaciones.index', compact(
            'vacaciones', 'empleados', 'patrono', 'porPagina'
        ));
    }

    /** Pantalla para crear una nueva vacación (formulario Livewire full-page). */
    public function create(): View
    {
        $patrono = $this->patronoActual();

        return view('vacaciones.create', compact('patrono'));
    }

    /** Detalle de una vacación con conceptos y acciones de estatus. */
    public function show(Vacacion $vacacion, Request $request): View
    {
        abort_if((int) $vacacion->patrono_id !== (int) session('patrono_id'), 404);

        $vacacion->load('empleado');
        $empleado = $vacacion->empleado;

        if (in_array($vacacion->estatus_vacacion, ['pagada', 'disfrutada'], true)) {
            $recibo = $vacacion->recibos()->latest('fecha_emision')->first();
            $moneda = $recibo
                ? $this->service->formatearMonto($empleado, (float) $recibo->neto, (float) $recibo->tipo_cambio, null, $vacacion->moneda)
                : null;
        } else {
            $recibo = null;
            $moneda = $this->service->formatearMonto($empleado, (float) $vacacion->salario_normal, null, null, $vacacion->moneda);
        }

        return view('vacaciones.show', compact('vacacion', 'empleado', 'recibo', 'moneda'));
    }

    /** Transición de estados: aprobar / anular / pagar. */
    public function estatus(Request $request, Vacacion $vacacion): RedirectResponse
    {
        abort_if((int) $vacacion->patrono_id !== (int) session('patrono_id'), 404);

        $action = $request->validate([
            'accion' => ['required', 'in:aprobar,anular,pagar'],
        ])['accion'];

        try {
            match ($action) {
                'aprobar' => $this->accionAprobar($vacacion),
                'anular'  => $this->accionAnular($vacacion),
                'pagar'   => $this->accionPagar($vacacion),
            };
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vacaciones.show', $vacacion)
            ->with('success', 'Estatus actualizado correctamente.');
    }

    /** PDF del recibo de pago (regenera o sobre recibo existente). */
    public function reciboPdf(Vacacion $vacacion)
    {
        abort_if((int) $vacacion->patrono_id !== (int) session('patrono_id'), 404);

        $vacacion->load('empleado');
        $empleado = $vacacion->empleado;

        $recibo = in_array($vacacion->estatus_vacacion, ['pagada', 'disfrutada'], true)
            ? $vacacion->recibos()->latest('fecha_emision')->first()
            : null;

        if (! $recibo) {
            $recibo = $this->service->generarRecibo($vacacion);
        }

        $moneda = $this->service->formatearMonto($empleado, (float) $recibo->neto, (float) $recibo->tipo_cambio, null, $vacacion->moneda);

        return Pdf::loadView('vacaciones.pdf', [
            'vacacion'  => $vacacion,
            'empleado'  => $empleado,
            'recibo'    => $recibo,
            'moneda'    => $moneda,
            'asignaciones' => $recibo->asignaciones,
            'deducciones'  => $recibo->deducciones,
            'total_asignaciones' => (float) $recibo->total_asignaciones,
            'total_deducciones'  => (float) $recibo->total_deducciones,
        ])->setPaper('letter', 'portrait')
          ->stream("recibo-vacaciones-{$empleado->nombre_completo}.pdf");
    }

    /** Elimina un periodo pendiente sin recibo. */
    public function destroy(Vacacion $vacacion): RedirectResponse
    {
        abort_if((int) $vacacion->patrono_id !== (int) session('patrono_id'), 404);

        if (! $vacacion->puedeBorrar()) {
            return redirect()->route('vacaciones.index')
                ->with('error', 'No se puede eliminar una vacación pagada, disfrutada o con recibo emitido.');
        }

        $vacacion->delete();

        return redirect()->route('vacaciones.index')
            ->with('success', 'Periodo de vacaciones eliminado.');
    }

    // ----- transiciones de estatus -----

    private function accionAprobar(Vacacion $vacacion): void
    {
        if (! $vacacion->puedeAprobar()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'accion' => 'El cálculo no puede aprobarse: ya está aprobado o disfrutado.',
            ]);
        }
        $vacacion->update(['estatus_calculo' => 'aprobada']);
    }

    private function accionAnular(Vacacion $vacacion): void
    {
        if (! $vacacion->puedeAnular()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'accion' => 'No se puede anular: la vacación ya fue pagada o disfrutada.',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($vacacion) {
            $vacacion->recibos()->delete();
            $vacacion->update(['estatus_calculo' => 'no_aprobada']);
        });
    }

    private function accionPagar(Vacacion $vacacion): void
    {
        if (! $vacacion->puedePagar()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'accion' => 'El cálculo no se encuentra aprobado o ya fue pagado.',
            ]);
        }

        $recibo = $this->service->generarRecibo($vacacion);

        $vacacion->update([
            'estatus_vacacion' => 'disfrutada',
            'estatus_calculo'  => 'aprobada',
        ]);

        $recibo->update([
            'fecha_inicio' => $vacacion->fecha_inicio_real,
            'fecha_retiro' => $vacacion->fecha_reintegro,
        ]);
    }
}
