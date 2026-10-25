<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\MovimientoPrestaciones;
use App\Models\Patrono;
use App\Services\MovimientosPrestacionesService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MovimientosPrestacionesController extends Controller
{
    public function __construct(
        private MovimientosPrestacionesService $service = new MovimientosPrestacionesService,
    ) {
    }

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    /** Listado de anticipos/préstamos del patrono en contexto. */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $query = MovimientoPrestaciones::with('empleado')
            ->where('patrono_id', $patrono->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($empleadoId = $request->filled('empleado_id') ? (int) $request->get('empleado_id') : null) {
            $query->where('empleado_id', $empleadoId);
        }

        if (in_array($request->get('tipo'), [MovimientoPrestaciones::TIPO_ANTICIPO, MovimientoPrestaciones::TIPO_PRESTAMO], true)) {
            $query->where('tipo', $request->get('tipo'));
        }

        if (in_array($request->get('estatus'), ['activo', 'pagado', 'anulado'], true)) {
            $query->where('estatus', $request->get('estatus'));
        }

        $movimientos = $query->paginate($porPagina)->withQueryString();

        $empleados = $patrono->empleados()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        return view('movimientos-prestaciones.index', compact(
            'movimientos', 'empleados', 'patrono', 'porPagina'
        ));
    }

    /** Pantalla para registrar un nuevo anticipo o préstamo. */
    public function create(Request $request): View
    {
        $patrono = $this->patronoActual();

        $empleado = null;
        if ($request->filled('empleado_id')) {
            $empleado = $patrono->empleados()->find((int) $request->get('empleado_id'));
            $empleado?->load('patrono');
            abort_if($empleado && ! $empleado->estaActivo(), 409, 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.');
        }

        return view('movimientos-prestaciones.create', compact('patrono', 'empleado'));
    }

    /** Guarda un nuevo movimiento. */
    public function store(Request $request): RedirectResponse
    {
        $patrono = $this->patronoActual();

        $validated = $request->validate($this->reglas($patrono, null));

        $empleado = $patrono->empleados()->findOrFail((int) $validated['empleado_id']);
        $empleado->load('patrono');

        abort_if(! $empleado->estaActivo(), 409, 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.');

        $movimiento = $this->service->crear($empleado, $validated);

        return redirect()->route('movimientos-prestaciones.show', $movimiento)
            ->with('success', 'Movimiento registrado correctamente.');
    }

    /** Consulta el máximo de anticipo de un empleado (JSON para la UI). */
    public function maximo(Request $request, int $empleadoId)
    {
        $patrono = $this->patronoActual();
        $empleado = $patrono->empleados()->findOrFail($empleadoId);
        $empleado->load('patrono');

        if (! $empleado->estaActivo()) {
            return response()->json(['error' => 'Trabajador inactivo (liquidado).'], 409);
        }

        $data = $request->validate([
            'fecha' => ['nullable', 'date'],
        ]);
        $fecha = ! empty($data['fecha']) ? Carbon::parse($data['fecha']) : null;
        $max = $this->service->maximoAnticipo($empleado, $fecha);

        return response()->json([
            'saldo'    => $max['saldo'],
            'maximo'   => $max['maximo'],
            'pct'      => $max['pct'],
            'monto_75' => round($max['saldo'] * 0.75, 2),
            'pct_75'   => 75,
            'moneda'   => $max['moneda'],
            'dias'     => $max['dias'],
            'anios'    => $max['anios'],
            'fecha'    => ($fecha ?: now())->format('d/m/Y'),
        ]);
    }

    /** Detalle de un movimiento con sus intereses pagados. */
    public function show(MovimientoPrestaciones $movimiento, Request $request): View    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        $movimiento->load(['empleado', 'intereses']);

        return view('movimientos-prestaciones.show', compact('movimiento'));
    }

    /** Registra el pago de intereses de un movimiento. */
    public function registrarInteres(Request $request, MovimientoPrestaciones $movimiento): RedirectResponse
    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        $validated = $request->validate([
            'fecha'   => ['required', 'date'],
            'monto'   => ['required', 'numeric', 'min:0.01'],
            'moneda'  => ['required', 'in:USD,BS'],
            'concepto'=> ['nullable', 'string', 'max:255'],
        ]);

        $this->service->registrarInteres(
            $movimiento,
            Carbon::parse($validated['fecha']),
            (float) $validated['monto'],
            $validated['moneda'],
            $validated['concepto'] ?? null,
        );

        return back()->with('success', 'Interés registrado correctamente.');
    }

    /** Abona a un préstamo (reduce saldo). */
    public function abonar(Request $request, MovimientoPrestaciones $movimiento): RedirectResponse
    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        if ($movimiento->esAnticipo()) {
            abort(409, 'Un anticipo no admite abonos.');
        }

        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->service->pagarPrestamo($movimiento, (float) $validated['monto']);

        return back()->with('success', 'Abono registrado correctamente.');
    }

    /** Anula un movimiento pendiente. */
    public function anular(Request $request, MovimientoPrestaciones $movimiento): RedirectResponse
    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        try {
            $this->service->anular($movimiento);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Movimiento anulado correctamente.');
    }

    /** Elimina definitivamente un movimiento anulado. */
    public function destroy(MovimientoPrestaciones $movimiento): RedirectResponse
    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        if ($movimiento->estatus !== 'anulado') {
            return redirect()->route('movimientos-prestaciones.index')
                ->with('error', 'Anule el movimiento antes de eliminarlo.');
        }

        $movimiento->delete();

        return redirect()->route('movimientos-prestaciones.index')
            ->with('success', 'Movimiento eliminado.');
    }

    /** Reglas de validación compartidas. */
    private function reglas(Patrono $patrono, ?int $ignoreId): array
    {
        return [
            'empleado_id'  => ['required', 'integer', Rule::exists('empleados', 'id')->where('patrono_id', $patrono->id)],
            'tipo'         => ['required', Rule::in([MovimientoPrestaciones::TIPO_ANTICIPO, MovimientoPrestaciones::TIPO_PRESTAMO])],
            'fecha'        => ['required', 'date'],
            'concepto'     => ['nullable', 'string', 'max:255'],
            'motivo'       => ['nullable', Rule::in(MovimientoPrestaciones::MOTIVOS_ANTICIPO)],
            'monto'        => ['required', 'numeric', 'min:0.01'],
            'moneda'       => ['required', 'in:USD,BS'],
            'plazo_meses'  => ['nullable', 'integer', 'min:1', 'max:120'],
            'interes_anual'=> ['nullable', 'numeric', 'min:0', 'max:100'],
            'observacion'  => ['nullable', 'string'],
        ];
    }
}
