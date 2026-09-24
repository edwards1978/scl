<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patrono;
use App\Services\VacacionesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportesController extends Controller
{
    public function __construct(
        private VacacionesService $service = new VacacionesService,
    ) {
    }

    private function patronoActual(): Patrono
    {
        return Patrono::findOrFail((int) session('patrono_id'));
    }

    private function filtros(Request $request): array
    {
        return [
            'ano'             => $request->filled('ano') ? (int) $request->get('ano') : null,
            'empleado_id'     => $request->filled('empleado_id') ? (int) $request->get('empleado_id') : null,
            'estatus_calculo' => in_array($request->get('estatus_calculo'), ['por_aprobar', 'aprobada', 'no_aprobada'], true)
                ? $request->get('estatus_calculo')
                : null,
            'estatus_vacacion' => in_array($request->get('estatus_vacacion'), ['pendiente', 'pagada', 'disfrutada'], true)
                ? $request->get('estatus_vacacion')
                : null,
        ];
    }

    /** Reporte de vacaciones (historial + estadísticas), bajo contexto patrono. */
    public function vacaciones(Request $request): View
    {
        $patrono = $this->patronoActual();
        $filtros = $this->filtros($request);
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $vacaciones = $this->service->reporteVacaciones($patrono, $filtros, $porPagina);
        $estadisticas = $this->service->estadisticasVacaciones($patrono, $filtros);

        $empleados = $patrono->empleados()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cedula']);

        return view('reportes.vacaciones.index', compact(
            'patrono', 'vacaciones', 'estadisticas', 'empleados', 'porPagina'
        ));
    }

    /** Exporta el reporte a PDF institucional en Letter (apaisado). */
    public function vacacionesPdf(Request $request)
    {
        $patrono = $this->patronoActual();
        $filtros = $this->filtros($request);

        $vacaciones = $this->service->reporteVacacionesExport($patrono, $filtros)
            ->map(function ($v) {
                $recibo = $v->recibos()->latest('fecha_emision')->first();

                return [
                    'trabajador'        => $v->empleado->nombre_completo ?? '—',
                    'cedula'            => $v->empleado->cedula ?? '—',
                    'periodo_trabajado' => $v->periodo_desde->format('d/m/Y').' — '.$v->periodo_hasta->format('d/m/Y'),
                    'disfrute'          => $v->fecha_inicio_real?->format('d/m/Y').' — '.$v->fecha_fin_real?->format('d/m/Y'),
                    'reintegro'         => $v->fecha_reintegro?->format('d/m/Y') ?? '—',
                    'dias_vacaciones'   => $v->diasHabilesDisfrute(),
                    'dias_bono'         => $v->diasHabilesBono(),
                    'salario_normal'    => $v->salario_normal,
                    'moneda'            => $v->moneda,
                    'estatus_calculo'   => $v->labelEstatusCalculo(),
                    'estatus_vacacion'  => $v->labelEstatusVacacion(),
                    'neto'              => $recibo ? (float) $recibo->neto : null,
                ];
            })
            ->values();

        $estadisticas = $this->service->estadisticasVacaciones($patrono, $filtros);

        $pdf = Pdf::loadView('reportes.vacaciones.pdf', [
            'patrono'      => $patrono,
            'vacaciones'   => $vacaciones,
            'estadisticas' => $estadisticas,
        ])->setPaper('letter', 'landscape');

        return $pdf->stream('reporte-vacaciones-'.$patrono->razon_social.'.pdf');
    }
}