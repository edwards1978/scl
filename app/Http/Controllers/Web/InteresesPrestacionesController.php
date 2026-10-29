<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Services\PrestacionesSocialesService;
use App\Services\TasasBcvService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InteresesPrestacionesController extends Controller
{
    public function __construct(
        private PrestacionesSocialesService $prestaciones = new PrestacionesSocialesService,
        private TasasBcvService $tasas = new TasasBcvService,
    ) {}

    /** Listado de pagos de intereses del patrono en contexto. */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $filtros = $request->validate([
            'empleado_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $consulta = PagoInteresPrestaciones::with('empleado')
            ->where('patrono_id', $patrono->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if (! empty($filtros['empleado_id'])) {
            $consulta->where('empleado_id', (int) $filtros['empleado_id']);
        }
        if (! empty($filtros['desde'])) {
            $consulta->whereDate('fecha', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $consulta->whereDate('fecha', '<=', $filtros['hasta']);
        }

        $totales = (clone $consulta)
            ->reorder()
            ->toBase()
            ->select('moneda', DB::raw('SUM(monto) as total'))
            ->groupBy('moneda')
            ->pluck('total', 'moneda');

        $pagos = $consulta->paginate($porPagina)->withQueryString();

        $empleados = $patrono->empleados()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        $empleadoFiltro = ! empty($filtros['empleado_id'])
            ? $patrono->empleados()->find((int) $filtros['empleado_id'])
            : null;

        return view('intereses-prestaciones.index', compact(
            'patrono', 'pagos', 'empleados', 'empleadoFiltro', 'filtros', 'totales', 'porPagina'
        ));
    }

    /** Pantalla para registrar un nuevo pago de intereses. */
    public function create(Request $request): View
    {
        Gate::authorize('gestionar');

        $patrono = $this->patronoActual();

        $empleados = $patrono->empleados()
            ->where('estatus', 'Activo')
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        $empleado = null;
        if ($request->filled('empleado_id')) {
            $empleado = $patrono->empleados()->find((int) $request->get('empleado_id'));
            $empleado?->load('patrono');
            abort_if(
                $empleado && ! $empleado->estaActivo(),
                409,
                'El trabajador está inactivo (ya liquidado): no se pueden registrar pagos de intereses.'
            );
        }

        return view('intereses-prestaciones.create', compact('patrono', 'empleados', 'empleado'));
    }

    /**
     * Interés pendiente del trabajador a una fecha (JSON para la UI).
     * Es de solo lectura: no genera ni recalcula períodos.
     */
    public function saldo(Request $request, int $empleadoId): JsonResponse
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
        $saldo = $this->prestaciones->interesPendienteEn($empleado, $fecha);

        return response()->json([
            'fecha' => ($fecha ?: now())->format('d/m/Y'),
            'periodo' => $saldo['periodo'],
            'moneda' => $saldo['moneda'],
            'pendiente' => $saldo['pendiente'],
            'tasa' => $saldo['tasa'],
            'usd' => $saldo['usd'],
            'bs' => $saldo['bs'],
            'es_proyeccion' => $saldo['es_proyeccion'],
        ]);
    }

    /** Registra un pago de intereses y recalcula las prestaciones. */
    public function store(Request $request): RedirectResponse
    {
        $patrono = $this->patronoActual();

        $request->merge([
            'monto' => (string) parsear_numero((string) $request->input('monto')),
        ]);

        $data = $request->validate([
            'empleado_id' => ['required', 'integer', Rule::exists('empleados', 'id')->where('patrono_id', $patrono->id)],
            'fecha' => ['required', 'date', 'before_or_equal:'.now()->addMonths(12)->endOfMonth()->toDateString()],
            'fecha_comprobante' => ['nullable', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'moneda' => ['required', 'in:USD,BS'],
            'comprobante' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $empleado = $patrono->empleados()->findOrFail((int) $data['empleado_id']);
        $empleado->load('patrono');
        abort_if(
            ! $empleado->estaActivo(),
            409,
            'El trabajador está inactivo (ya liquidado): no se pueden registrar pagos de intereses.'
        );

        $fecha = Carbon::parse($data['fecha']);
        $tasa = $data['moneda'] === 'USD' ? $this->tasas->tasaEn($fecha) : null;

        DB::transaction(function () use ($empleado, $data, $fecha, $tasa) {
            PagoInteresPrestaciones::create([
                'patrono_id' => $empleado->patrono_id,
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'fecha_comprobante' => $data['fecha_comprobante'] ?? now()->toDateString(),
                'monto' => $data['monto'],
                'moneda' => $data['moneda'],
                'tasa_cambio' => $tasa,
                'comprobante' => $data['comprobante'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $periodo = $this->prestaciones->periodoDeFecha($empleado, $fecha)
                ?? $fecha->format('Y-m');
            $this->prestaciones->recalcularDesde($empleado, $periodo);
        });

        return redirect()->route('intereses-prestaciones.index', ['empleado_id' => $empleado->id])
            ->with('success', 'Pago de intereses registrado y prestaciones recalculadas.');
    }

    /** Detalle de un pago de intereses con acceso a su comprobante. */
    public function show(PagoInteresPrestaciones $pago): View
    {
        abort_if((int) $pago->patrono_id !== (int) session('patrono_id'), 404);

        $pago->load(['empleado', 'patrono']);

        return view('intereses-prestaciones.show', compact('pago'));
    }

    /** Comprobante/recibo de pago de intereses en PDF. */
    public function comprobante(PagoInteresPrestaciones $pago)
    {
        abort_if((int) $pago->patrono_id !== (int) session('patrono_id'), 404);

        $pago->load(['empleado', 'patrono']);

        $archivo = 'comprobante-intereses-'
            .(Str::slug($pago->empleado->nombre_completo) ?: 'trabajador')
            .'-'.$pago->id.'.pdf';

        return Pdf::loadView('intereses-prestaciones.comprobante', compact('pago'))
            ->setPaper('letter', 'portrait')
            ->stream($archivo);
    }

    /** Elimina un pago de intereses y recalcula las prestaciones. */
    public function destroy(Request $request, int $pago): RedirectResponse
    {
        Gate::authorize('eliminar');

        $patrono = $this->patronoActual();

        $pago = PagoInteresPrestaciones::where('patrono_id', $patrono->id)->findOrFail($pago);
        $empleado = $pago->empleado;

        abort_if(! $empleado, 404);

        DB::transaction(function () use ($pago, $empleado) {
            $periodo = $this->prestaciones->periodoDeFecha($empleado, $pago->fecha)
                ?? $pago->fecha->format('Y-m');
            $pago->delete();
            $this->prestaciones->recalcularDesde($empleado, $periodo);
        });

        // Desde el detalle no se puede volver a la ficha del pago eliminado.
        if ($request->input('volver_a') === 'index') {
            return redirect()->route('intereses-prestaciones.index')
                ->with('success', 'Pago de intereses eliminado y prestaciones recalculadas.');
        }

        return back()->with('success', 'Pago de intereses eliminado y prestaciones recalculadas.');
    }

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }
}
