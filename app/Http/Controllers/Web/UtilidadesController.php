<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BonificacionFinAno;
use App\Models\Patrono;
use App\Services\UtilidadesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UtilidadesController extends Controller
{
    public function __construct(
        private UtilidadesService $service = new UtilidadesService,
    ) {}

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    private function bonificacionDePatrono(int $id): BonificacionFinAno
    {
        $bonificacion = BonificacionFinAno::with('empleado')->findOrFail($id);

        abort_if((int) $bonificacion->patrono_id !== (int) session('patrono_id'), 404);

        return $bonificacion;
    }

    /**
     * Listado general de bonificaciones de fin de año del patrono, filtrado
     * por año (por defecto el vigente) o con la opción "todos".
     */
    public function index(Request $request): View
    {
        $patrono = $this->patronoActual();
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $anio = $request->string('anio')->toString();
        $anio = ($anio === '' || $anio === 'todos')
            ? 'todos'
            : (string) max((int) $anio, 2000);

        $query = BonificacionFinAno::with('empleado')
            ->where('patrono_id', $patrono->id)
            ->orderByDesc('anio')
            ->orderByDesc('id');

        if ($anio !== 'todos') {
            $query->where('anio', (int) $anio);
        }

        $pendientes = (clone $query)->where('estatus', 'calculado')->count();

        $bonificaciones = $query->paginate($porPagina)->withQueryString();

        $anios = $this->service->aniosDisponibles((int) $patrono->id);

        return view('utilidades.index', compact(
            'patrono', 'bonificaciones', 'anios', 'anio', 'porPagina', 'pendientes'
        ));
    }

    /**
     * Calcula (o recalcula) las bonificaciones de todos los trabajadores
     * activos del patrono para el año indicado.
     */
    public function calcular(Request $request): RedirectResponse
    {
        $patrono = $this->patronoActual();

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2200'],
            'tipo_base' => ['required', 'in:ultimo,promedio'],
        ]);

        $anio = (int) $data['anio'];
        $dias = max(1, (int) $patrono->dias_bonificacion);

        $procesados = 0;
        foreach ($patrono->empleados()->where('estatus', 'Activo')->get() as $empleado) {
            $this->service->calcularBonificacion($empleado, $anio, $dias, $data['tipo_base']);
            $procesados++;
        }

        return redirect()->route('utilidades.index', ['anio' => $anio])
            ->with('success', "Bonificación calculada para {$procesados} trabajador(es) del año {$anio}.");
    }

    /** Recalcula la bonificación de un trabajador (individual). */
    public function recalcular(Request $request, int $id): RedirectResponse
    {
        $bonificacion = $this->bonificacionDePatrono($id);
        $empleado = $bonificacion->empleado;

        abort_if(! $empleado->estaActivo(), 409, 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.');

        $tipoBase = in_array($request->get('tipo_base'), ['ultimo', 'promedio'], true)
            ? (string) $request->get('tipo_base')
            : 'ultimo';

        $this->service->calcularBonificacion(
            $empleado,
            (int) $bonificacion->anio,
            (int) $bonificacion->dias_base,
            $tipoBase
        );

        return redirect()->route('utilidades.index', ['anio' => $bonificacion->anio])
            ->with('success', "Bonificación de {$empleado->nombre_completo} recalculada.");
    }

    /** Aprueba la bonificación (habilita la emisión de recibo). */
    public function aprobar(int $id): RedirectResponse
    {
        $bonificacion = $this->bonificacionDePatrono($id);
        $this->service->emitirRecibo($bonificacion);

        return redirect()->route('utilidades.index', ['anio' => $bonificacion->anio])
            ->with('success', 'Bonificación aprobada.');
    }

    /**
     * Aprueba en bloque las bonificaciones pendientes del patrono,
     * respetando el filtro de año activo en el listado.
     */
    public function aprobarTodas(Request $request): RedirectResponse
    {
        $patrono = $this->patronoActual();

        $anio = $request->input('anio');
        $anio = in_array($anio, [null, '', 'todos'], true) ? null : (int) $anio;

        $aprobadas = $this->service->aprobarPendientes($patrono, $anio);

        $redireccion = redirect()->route('utilidades.index', ['anio' => $anio ?? 'todos']);

        if ($aprobadas === 0) {
            return $redireccion->with('info', 'No hay bonificaciones pendientes por aprobar.');
        }

        $mensaje = "Se aprobaron {$aprobadas} bonificación(es) pendiente(s)";

        return $redireccion->with(
            'success',
            $anio !== null ? "{$mensaje} del año {$anio}." : "{$mensaje}."
        );
    }

    /** Vista del recibo (pantalla) de una bonificación aprobada. */
    public function show(int $id): View
    {
        $bonificacion = $this->bonificacionDePatrono($id);
        $empleado = $bonificacion->empleado;

        $moneda = $this->service->formatearMonto($empleado, (float) $bonificacion->neto, (float) $bonificacion->tasa_cambio, $bonificacion);

        return view('utilidades.show', compact('bonificacion', 'empleado', 'moneda'));
    }

    /** PDF del recibo (formato vacaciones), en la moneda solicitada. */
    public function pdf(Request $request, int $id)
    {
        $bonificacion = $this->bonificacionDePatrono($id);
        $empleado = $bonificacion->empleado;

        // La moneda de emisión es la del sueldo del trabajador (regla del plan).
        $moneda = $this->service->formatearMonto($empleado, (float) $bonificacion->neto, (float) $bonificacion->tasa_cambio, $bonificacion);

        $patrono = $bonificacion->patrono;

        return Pdf::loadView('utilidades.pdf', [
            'bonificacion' => $bonificacion,
            'empleado' => $empleado,
            'patrono' => $patrono,
            'moneda' => $moneda,
            'asignaciones' => $bonificacion->asignaciones ?? [],
            'deducciones' => $bonificacion->deducciones ?? [],
            'total_asignaciones' => (float) $bonificacion->total_asignaciones,
            'total_deducciones' => (float) $bonificacion->total_deducciones,
            'fecha_emision' => $bonificacion->fecha_emision ?? Carbon::now(),
        ])->setPaper('letter', 'portrait')
            ->stream("bono-fin-anio-{$empleado->nombre_completo}.pdf");
    }

    /** Elimina el cálculo de bonificación de un trabajador (individual). */
    public function destroy(int $id): RedirectResponse
    {
        $bonificacion = $this->bonificacionDePatrono($id);
        $anio = (int) $bonificacion->anio;

        if ($bonificacion->estatus === 'aprobado') {
            return redirect()->route('utilidades.index', ['anio' => $anio])
                ->with('error', 'No se puede eliminar una bonificación aprobada y emitida.');
        }

        $bonificacion->delete();

        return redirect()->route('utilidades.index', ['anio' => $anio])
            ->with('success', 'Bonificación eliminada.');
    }
}
