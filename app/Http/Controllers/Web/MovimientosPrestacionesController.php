<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\MovimientoPrestaciones;
use App\Models\Patrono;
use App\Services\MovimientosPrestacionesService;
use App\Services\RetornoPrestacionesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MovimientosPrestacionesController extends Controller
{
    public function __construct(
        private MovimientosPrestacionesService $service = new MovimientosPrestacionesService,
        private RetornoPrestacionesService $retorno = new RetornoPrestacionesService,
    ) {}

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    /** Listado de anticipos/préstamos del patrono en contexto. */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $camposOrden = [
            'fecha' => 'Fecha movimiento',
            'fecha_comprobante' => 'Fecha comprobante',
            'monto' => 'Monto',
            'tipo' => 'Tipo',
            'estatus' => 'Estatus',
            'motivo' => 'Motivo',
            'trabajador' => 'Trabajador',
        ];
        $orden = array_key_exists((string) $request->get('orden'), $camposOrden)
            ? (string) $request->get('orden')
            : 'fecha';
        $direccion = $request->get('direccion') === 'asc' ? 'asc' : 'desc';

        $query = MovimientoPrestaciones::with('empleado')
            ->where('patrono_id', $patrono->id);

        if ($orden === 'trabajador') {
            $query->orderBy(
                Empleado::select('nombres')->whereColumn('empleados.id', 'movimientos_prestaciones.empleado_id'),
                $direccion
            )->orderBy(
                Empleado::select('apellidos')->whereColumn('empleados.id', 'movimientos_prestaciones.empleado_id'),
                $direccion
            );
        } else {
            $query->orderBy($orden, $direccion);
        }

        $query->orderByDesc('id');

        if ($empleadoId = $request->filled('empleado_id') ? (int) $request->get('empleado_id') : null) {
            $query->where('empleado_id', $empleadoId);
        }

        if (in_array($request->get('tipo'), [MovimientoPrestaciones::TIPO_ANTICIPO, MovimientoPrestaciones::TIPO_PRESTAMO], true)) {
            $query->where('tipo', $request->get('tipo'));
        }

        if (in_array($request->get('estatus'), ['activo', 'pagado', 'anulado'], true)) {
            $query->where('estatus', $request->get('estatus'));
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->get('fecha'));
        }

        if ($request->filled('fecha_comprobante')) {
            $query->whereDate('fecha_comprobante', $request->get('fecha_comprobante'));
        }

        if (in_array($request->get('motivo'), MovimientoPrestaciones::MOTIVOS_ANTICIPO, true)) {
            $query->where('motivo', $request->get('motivo'));
        }

        $totales = (clone $query)
            ->reorder()
            ->toBase()
            ->select('moneda', DB::raw('SUM(monto) as total'))
            ->groupBy('moneda')
            ->pluck('total', 'moneda');

        $movimientos = $query->paginate($porPagina)->withQueryString();

        $empleados = $patrono->empleados()
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        return view('movimientos-prestaciones.index', compact(
            'movimientos', 'empleados', 'patrono', 'porPagina', 'totales', 'camposOrden', 'orden', 'direccion'
        ));
    }

    /** Pantalla para registrar un nuevo anticipo o préstamo. */
    public function create(Request $request): View
    {
        Gate::authorize('gestionar');

        $patrono = $this->patronoActual();

        $empleado = null;
        if ($request->filled('empleado_id')) {
            $empleado = $patrono->empleados()->find((int) $request->get('empleado_id'));
            $empleado?->load('patrono');
            abort_if($empleado && ! $empleado->estaActivo(), 409, 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.');
        }

        $prefill = [
            'empleado_id' => $empleado?->id,
            'tipo' => in_array($request->get('tipo'), [MovimientoPrestaciones::TIPO_ANTICIPO, MovimientoPrestaciones::TIPO_PRESTAMO], true)
                ? $request->get('tipo')
                : MovimientoPrestaciones::TIPO_ANTICIPO,
            'fecha' => $this->fechaDePrefill($request->get('fecha')) ?? now()->format('Y-m-d'),
            'fecha_comprobante' => now()->format('Y-m-d'),
            'monto' => is_numeric($request->get('monto')) ? (float) $request->get('monto') : null,
            'moneda' => in_array($request->get('moneda'), ['USD', 'BS'], true)
                ? $request->get('moneda')
                : ($empleado?->moneda ?? 'USD'),
        ];

        $volverUrl = $empleado
            ? $this->retorno->url($request, $empleado)
            : null;

        return view('movimientos-prestaciones.create', compact('patrono', 'empleado', 'prefill', 'volverUrl'));
    }

    private function fechaDePrefill(mixed $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
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
        $volverUrl = $this->retorno->url($request, $empleado);

        if ($volverUrl !== null) {
            return redirect()->to($volverUrl)
                ->with('success', 'Movimiento registrado correctamente.');
        }

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
            'moneda' => ['nullable', 'in:USD,BS'],
        ]);
        $fecha = ! empty($data['fecha']) ? Carbon::parse($data['fecha']) : null;
        $max = $this->service->maximoAnticipo($empleado, $fecha);

        $moneda = $data['moneda'] ?? $max['moneda'];
        $maximo = $max['moneda'] === $moneda
            ? (float) $max['maximo']
            : $this->service->convertir((float) $max['maximo'], $max['moneda'], $moneda, $fecha);
        $saldo = $max['moneda'] === $moneda
            ? (float) $max['saldo']
            : $this->service->convertir((float) $max['saldo'], $max['moneda'], $moneda, $fecha);

        return response()->json([
            'saldo' => round($saldo, 2),
            'maximo' => round($maximo, 2),
            'pct' => $max['pct'],
            'monto_75' => round($maximo * 0.75, 2),
            'pct_75' => 75,
            'moneda' => $moneda,
            'dias' => $max['dias'],
            'anios' => $max['anios'],
            'es_proyeccion' => $max['es_proyeccion'],
            'fecha' => ($fecha ?: now())->format('d/m/Y'),
        ]);
    }

    /** Detalle de un movimiento con sus intereses pagados. */
    public function show(MovimientoPrestaciones $movimiento, Request $request): View
    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        $movimiento->load(['empleado', 'intereses']);

        return view('movimientos-prestaciones.show', compact('movimiento'));
    }

    /** Comprobante/recibo de pago del movimiento en PDF. */
    public function comprobante(MovimientoPrestaciones $movimiento)
    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        $movimiento->load(['empleado', 'patrono', 'intereses']);

        $archivo = 'comprobante-'.($movimiento->labelTipo() === 'Anticipo' ? 'anticipo' : 'prestamo')
            .'-'.(Str::slug($movimiento->empleado->nombre_completo) ?: 'trabajador')
            .'-'.$movimiento->id.'.pdf';

        return Pdf::loadView('movimientos-prestaciones.comprobante', compact('movimiento'))
            ->setPaper('letter', 'portrait')
            ->download($archivo);
    }

    /** Registra el pago de intereses de un movimiento. */
    public function registrarInteres(Request $request, MovimientoPrestaciones $movimiento): RedirectResponse
    {
        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);
        abort_if($movimiento->esAnticipo(), 409, 'Los anticipos no generan intereses.');

        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'moneda' => ['required', 'in:USD,BS'],
            'concepto' => ['nullable', 'string', 'max:255'],
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
        Gate::authorize('aprobar');

        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        try {
            $this->service->anular($movimiento);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Movimiento anulado correctamente.');
    }

    /** Elimina definitivamente un movimiento anulado. */
    public function destroy(MovimientoPrestaciones $movimiento): RedirectResponse
    {
        Gate::authorize('eliminar');

        abort_if((int) $movimiento->patrono_id !== (int) session('patrono_id'), 404);

        if ($movimiento->estatus !== 'anulado') {
            return redirect()->route('movimientos-prestaciones.index')
                ->with('error', 'Anule el movimiento antes de eliminarlo.');
        }

        $this->service->eliminar($movimiento);

        return redirect()->route('movimientos-prestaciones.index')
            ->with('success', 'Movimiento eliminado.');
    }

    /** Reglas de validación compartidas. */
    private function reglas(Patrono $patrono, ?int $ignoreId): array
    {
        $limiteProyeccion = now()->addMonths(12)->endOfMonth()->toDateString();

        return [
            'empleado_id' => ['required', 'integer', Rule::exists('empleados', 'id')->where('patrono_id', $patrono->id)],
            'tipo' => ['required', Rule::in([MovimientoPrestaciones::TIPO_ANTICIPO, MovimientoPrestaciones::TIPO_PRESTAMO])],
            'fecha' => ['required', 'date', 'before_or_equal:'.$limiteProyeccion],
            'fecha_comprobante' => ['nullable', 'date'],
            'concepto' => ['nullable', 'string', 'max:255'],
            'motivo' => ['nullable', Rule::in(MovimientoPrestaciones::MOTIVOS_ANTICIPO)],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'moneda' => ['required', 'in:USD,BS'],
            'plazo_meses' => ['nullable', 'integer', 'min:1', 'max:120'],
            'interes_anual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
