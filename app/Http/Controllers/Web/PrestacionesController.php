<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use App\Services\PrestacionesSocialesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Ods;

class PrestacionesController extends Controller
{
    public function __construct(
        private PrestacionesSocialesService $service = new PrestacionesSocialesService,
    ) {}

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

        $query = $patrono->empleados();

        if ($buscar = trim((string) $request->get('buscar'))) {
            $query->where(function ($q) use ($buscar) {
                $q->where('nombres', 'like', "%{$buscar}%")
                    ->orWhere('apellidos', 'like', "%{$buscar}%")
                    ->orWhere('cedula', 'like', "%{$buscar}%");
            });
        }

        if (in_array($request->get('estatus'), ['Activo', 'Inactivo', 'Suspendido'], true)) {
            $query->where('estatus', $request->get('estatus'));
        }

        $empleados = $query
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->paginate($porPagina)
            ->withQueryString();

        $saldos = [];
        $calculadoHasta = [];
        foreach ($empleados as $empleado) {
            // El saldo debe corresponder al cierre indicado en "Calculado hasta":
            // último período guardado, real o proyectado.
            $saldos[$empleado->id] = $this->service->saldoConProyeccion($empleado);
            $calculadoHasta[$empleado->id] = $this->service->ultimoCierreConProyeccion($empleado);
        }

        $activos = $patrono->empleados()->where('estatus', 'Activo')->count();
        $proyeccionHasta = PrestacionSocial::where('patrono_id', $patrono->id)
            ->where('es_proyeccion', true)
            ->whereHas('empleado', fn ($query) => $query->where('estatus', 'Activo'))
            ->max('periodo');
        $minProyeccion = now()->addMonth()->format('Y-m');
        $maxProyeccion = now()->addMonths(12)->format('Y-m');

        return view('prestaciones.index', compact(
            'patrono', 'empleados', 'saldos', 'calculadoHasta', 'porPagina',
            'activos', 'proyeccionHasta', 'minProyeccion', 'maxProyeccion'
        ));
    }

    /** Estado de cuenta mensual de un empleado, con filtro opcional por período. */
    public function show(Request $request, int $empleadoId): View
    {
        $empleado = $this->empleadoDePatrono($empleadoId);
        $filtros = $this->filtrosPeriodo($request);
        // La pantalla muestra por defecto hasta la última proyección guardada:
        // así la proyección persiste visible hasta eliminarla o recalcular.
        $hastaConsulta = $filtros['hasta'] ?? $this->service->ultimoPeriodoConProyeccion($empleado);
        $rango = $this->service->estadoDeCuentaEnRango($empleado, $filtros['desde'], $hastaConsulta);
        $incluyeProyeccion = $rango['filas']->contains(fn ($c) => (bool) $c->es_proyeccion);

        $incluirAnteriores = $filtros['incluir_anteriores'];
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $hayRango = $rango['hay_rango'];
        $cuenta = $this->service->filasConSaldosDelPeriodo(
            $rango['filas'],
            $hayRango && $incluirAnteriores
        );

        // Los movimientos siempre son del período. El checkbox solo agrega
        // el saldo acumulado que existía antes de "desde".
        $totales = $rango['totales_periodo'];
        $netoPeriodo = $totales['neto'];
        $saldoInicialRango = ($hayRango && $incluirAnteriores)
            ? $rango['saldo_inicial']
            : 0.0;
        $saldoFinalRango = $this->saldoFinalDeRango($cuenta, $saldoInicialRango);
        $saldoActual = $saldoFinalRango;
        $interesPendiente = $rango['interes_pendiente'];
        $totalDepositos = $totales['depositos'];
        $totalAdicionales = $totales['adicionales'];
        $totalIntereses = $totales['intereses'];
        $totalInteresesCapitalizados = $totales['capitalizado'];
        $totalInteresesPagados = $totales['pagados'];
        $totalAnticipos = $totales['anticipos'];

        $periodosFiltrados = $rango['periodos_filtrados'];
        $totalPeriodos = $rango['total_periodos'];
        $totalesPeriodo = $rango['totales_periodo'];
        $periodoDesde = $rango['desde'];
        $periodoHasta = $rango['hasta'];
        $proyeccionHasta = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', true)
            ->max('periodo');
        $maxProyeccion = now()->addMonths(12)->format('Y-m');

        return view('prestaciones.show', compact(
            'empleado', 'cuenta', 'saldoActual', 'saldoInicialRango', 'saldoFinalRango',
            'totalDepositos', 'totalAdicionales', 'totalIntereses', 'totalAnticipos',
            'totalInteresesCapitalizados', 'totalInteresesPagados', 'interesPendiente', 'netoPeriodo',
            'desde', 'hasta', 'incluirAnteriores', 'hayRango', 'periodosFiltrados', 'totalPeriodos',
            'totalesPeriodo', 'periodoDesde', 'periodoHasta',
            'proyeccionHasta', 'maxProyeccion', 'incluyeProyeccion'
        ));
    }

    /**
     * Saldo acumulado al cierre del rango, tomado de la última fila ya
     * rebasada para la vista. No se deriva de saldo inicial + neto porque un
     * pago de intereses que excede el interés pendiente y el capital
     * disponible no reduce el saldo (el exceso queda sin aplicar).
     */
    private function saldoFinalDeRango(Collection $cuenta, float $saldoInicialRango): float
    {
        $ultima = $cuenta->last();

        return $ultima !== null
            ? round((float) $ultima->saldo_final_periodo, 2)
            : round($saldoInicialRango, 2);
    }

    /**
     * Valida y normaliza los filtros de período.
     *
     * @return array{desde:?string, hasta:?string, incluir_anteriores:bool}
     */
    private function filtrosPeriodo(Request $request): array
    {
        $data = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m'],
            'hasta' => ['nullable', 'date_format:Y-m'],
        ]);

        $desde = $data['desde'] ?? null;
        $hasta = $data['hasta'] ?? null;

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw ValidationException::withMessages([
                'hasta' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            ]);
        }

        // Nuevo parámetro: incluir o no los saldos acumulados anteriores a
        // "desde". Por defecto se incluyen; el usuario puede desmarcarlo para
        // totalizar solo el período seleccionado. Se acepta "solo_periodo" de
        // enlaces antiguos, invertido.
        $incluirAnteriores = true;
        if ($request->has('incluir_anteriores')) {
            $incluirAnteriores = $request->boolean('incluir_anteriores');
        } elseif ($request->has('solo_periodo')) {
            $incluirAnteriores = ! $request->boolean('solo_periodo');
        }

        return ['desde' => $desde, 'hasta' => $hasta, 'incluir_anteriores' => $incluirAnteriores];
    }

    /** Genera/guarda la proyección de todos los trabajadores activos del patrono. */
    public function proyectar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hasta' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $resultado = $this->service->proyectarActivos($this->patronoActual(), $data['hasta']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('prestaciones.index')->with(
            'success',
            "Proyección generada hasta {$data['hasta']} para {$resultado['trabajadores']} trabajador(es) activo(s) ({$resultado['periodos']} período(s)). Se usa como base operativa para anticipos e intereses con fecha futura y emplea la última tasa y salario disponibles."
        );
    }

    /** Elimina las proyecciones guardadas del empleado. */
    public function eliminarProyeccion(int $empleadoId): RedirectResponse
    {
        $empleado = $this->empleadoActivo($empleadoId);
        $eliminados = $this->service->eliminarProyeccion($empleado);

        return redirect()->route('prestaciones.show', $empleado->id)
            ->with('success', "Proyección eliminada ({$eliminados} período(s)).");
    }

    /** Elimina un período proyectado individual del estado de cuenta. */
    public function eliminarProyeccionPeriodo(int $empleadoId, int $prestacionId): RedirectResponse
    {
        $empleado = $this->empleadoActivo($empleadoId);

        $prestacion = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', true)
            ->findOrFail($prestacionId);

        $periodo = $prestacion->periodo;
        $prestacion->delete();

        return redirect()->route('prestaciones.show', $empleado->id)
            ->with('success', "Período proyectado {$periodo} eliminado.");
    }

    /** Recalcula el estado de cuenta completo de un empleado. */
    public function recalcular(Request $request, int $empleadoId): RedirectResponse
    {
        $empleado = $this->empleadoActivo($empleadoId);

        $creados = $this->service->regenerar($empleado);

        return redirect()->route('prestaciones.show', $empleado->id)
            ->with('success', "Estado de cuenta de {$empleado->nombre_completo} recalculado ({$creados} periodo(s)).");
    }

    /** Exporta el estado de cuenta a OpenDocument Spreadsheet (.ods), con el mismo filtro. */
    public function hojaCalculo(Request $request, int $empleadoId)
    {
        $empleado = $this->empleadoDePatrono($empleadoId);
        $patrono = $this->patronoActual();
        $filtros = $this->filtrosPeriodo($request);
        // Sin filtro explícito, la hoja de cálculo cierra en el último período
        // guardado (real o proyectado), igual que la pantalla.
        $hastaConsulta = $filtros['hasta'] ?? $this->service->ultimoPeriodoConProyeccion($empleado);
        $rango = $this->service->estadoDeCuentaEnRango($empleado, $filtros['desde'], $hastaConsulta);
        $incluirAnteriores = $filtros['incluir_anteriores'];
        $pagos = PagoInteresPrestaciones::where('empleado_id', $empleado->id)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $moneda = $empleado->moneda;
        $desde = $rango['desde'];
        $hasta = $rango['hasta'];
        $hayRango = $rango['hay_rango'];
        $cuenta = $this->service->filasConSaldosDelPeriodo(
            $rango['filas'],
            $hayRango && $incluirAnteriores
        );
        $totalesPeriodo = $rango['totales_periodo'];
        $netoPeriodo = $totalesPeriodo['neto'];
        $saldoInicialRango = ($hayRango && $incluirAnteriores)
            ? $rango['saldo_inicial']
            : 0.0;
        $saldoFinalRango = $this->saldoFinalDeRango($cuenta, $saldoInicialRango);
        $saldoActual = $saldoFinalRango;
        $interesPendiente = $rango['interes_pendiente'];
        $proyeccionHasta = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', true)
            ->max('periodo');
        $incluyeProyeccion = $rango['filas']->contains(fn ($c) => (bool) $c->es_proyeccion);
        $etiquetaTotales = 'Totales del período';
        $estadoAnteriores = ! $hayRango
            ? 'Período completo'
            : ($incluirAnteriores ? 'Incluye saldos anteriores' : 'No incluye saldos anteriores');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SCL')
            ->setTitle('Estado de cuenta de prestaciones')
            ->setSubject('Prestaciones sociales');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Estado de cuenta');

        $sheet->mergeCells('A1:T1');
        $sheet->setCellValue('A1', $patrono->razon_social);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->mergeCells('A2:T2');
        $sheet->setCellValue('A2', 'RIF ('.$patrono->rif.')');
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->mergeCells('A3:T3');
        $sheet->setCellValue('A3', 'Estado de Cuenta de Prestaciones Sociales — Arts. 142 y 143 LOTTT');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

        $sheet->mergeCells('A4:T4');
        $sheet->setCellValue('A4', 'Período: '.($desde ?? '—').' a '.($hasta ?? '—')
            .' · '.$etiquetaTotales.' · '.$estadoAnteriores.' · '.$rango['periodos_filtrados'].' de '.$rango['total_periodos'].' período(s)'
            .($incluyeProyeccion ? ' · Incluye proyección hasta '.$proyeccionHasta : ''));
        $sheet->getStyle('A4')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

        $sheet->setCellValue('A5', 'Trabajador');
        $sheet->mergeCells('B5:D5');
        $sheet->setCellValue('B5', $empleado->nombre_completo);
        $sheet->setCellValue('E5', 'C.I.');
        $sheet->mergeCells('F5:H5');
        $sheet->setCellValue('F5', $empleado->cedula);
        $sheet->setCellValue('I5', 'Fecha de ingreso');
        $sheet->mergeCells('J5:L5');
        $sheet->setCellValue('J5', $empleado->fecha_ingreso?->format('d/m/Y') ?? '—');
        $sheet->setCellValue('M5', 'Moneda');
        $sheet->mergeCells('N5:T5');
        $sheet->setCellValue('N5', $moneda);

        $sheet->setCellValue('A6', 'Emitido');
        $sheet->mergeCells('B6:D6');
        $sheet->setCellValue('B6', now()->format('d/m/Y H:i'));
        $sheet->setCellValue('E6', 'Saldo al cierre ('.$hasta.')');
        $sheet->mergeCells('F6:H6');
        $sheet->setCellValue('F6', $saldoActual);
        $sheet->setCellValue('I6', 'Interés pendiente al cierre');
        $sheet->mergeCells('J6:L6');
        $sheet->setCellValue('J6', $interesPendiente);
        $sheet->getStyle('F6')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('J6')->getNumberFormat()->setFormatCode('#,##0.00');

        $sheet->setCellValue('A7', 'Saldo inicial del período');
        $sheet->mergeCells('B7:D7');
        $sheet->setCellValue('B7', $saldoInicialRango);
        $sheet->setCellValue('E7', 'Saldo final del período');
        $sheet->mergeCells('F7:H7');
        $sheet->setCellValue('F7', $saldoFinalRango);
        $sheet->setCellValue('I7', 'Neto del período');
        $sheet->mergeCells('J7:L7');
        $sheet->setCellValue('J7', $netoPeriodo);
        $sheet->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F7')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('J7')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');

        foreach (['A5', 'E5', 'I5', 'M5', 'A6', 'E6', 'I6', 'A7', 'E7', 'I7'] as $celda) {
            $sheet->getStyle($celda)->getFont()->setBold(true);
        }

        $headerRow = 9;

        $grupos = [
            'A:A' => 'Nº',
            'B:C' => 'Periodo',
            'D:F' => 'Tiempo de servicio',
        ];

        foreach ($grupos as $rango => $titulo) {
            [$colDesde, $colHasta] = explode(':', $rango);
            $sheet->mergeCells($colDesde.($headerRow - 1).':'.$colHasta.$headerRow);
            $sheet->setCellValue($colDesde.($headerRow - 1), $titulo);
        }

        $headers = [
            'Salario integral', 'Saldo inicial período',
            'Depósito trimestral', 'Días depósito', 'Días adicionales',
            'Monto días adicionales', 'Anticipos', 'Capital',
            'Tasa interés %', 'Interés del mes', 'Interés capitalizado',
            'Interés pagado', 'Interés pendiente', 'Saldo final período',
        ];

        foreach ($headers as $i => $header) {
            $columna = chr(71 + $i);
            $sheet->mergeCells($columna.($headerRow - 1).':'.$columna.$headerRow);
            $sheet->setCellValue($columna.($headerRow - 1), $header);
        }

        foreach (['Desde', 'Hasta', 'Años', 'Mes(es)', 'Día(s)'] as $i => $header) {
            $sheet->setCellValue(chr(66 + $i).$headerRow, $header);
        }

        $sheet->getStyle('A'.($headerRow - 1).':T'.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow - 1)->setRowHeight(20);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        $row = $headerRow + 1;
        $numero = 1;
        foreach ($cuenta as $c) {
            $sheet->setCellValue('A'.$row, $numero++);
            $sheet->setCellValue('B'.$row, $c->periodo_inicio->format('d/m/Y'));
            $sheet->setCellValue('C'.$row, $c->periodo_fin->format('d/m/Y').($c->es_proyeccion ? ' (proyección)' : ''));
            $sheet->setCellValue('D'.$row, (int) $c->servicio_anios);
            $sheet->setCellValue('E'.$row, (int) $c->servicio_meses);
            $sheet->setCellValue('F'.$row, (int) $c->servicio_dias);
            $sheet->setCellValue('G'.$row, (float) $c->salario_integral_diario);
            $sheet->setCellValue('H'.$row, (float) $c->saldo_inicial_periodo);
            $sheet->setCellValue('I'.$row, (float) $c->deposito_trimestre);
            $sheet->setCellValue('J'.$row, (int) $c->dias_deposito);
            $sheet->setCellValue('K'.$row, (int) $c->dias_adicionales_cont);
            $sheet->setCellValue('L'.$row, (float) $c->dias_adicionales);
            $sheet->setCellValue('M'.$row, -1 * (float) $c->anticipos);
            $sheet->setCellValue('N'.$row, (float) $c->capital_periodo);
            $sheet->setCellValue('O'.$row, $c->tasa_interes !== null ? (float) $c->tasa_interes : null);
            $sheet->setCellValue('P'.$row, (float) $c->interes_mes);
            $sheet->setCellValue('Q'.$row, (float) ($c->interes_capitalizado ?? 0));
            $sheet->setCellValue('R'.$row, (float) ($c->interes_pagado ?? 0));
            $sheet->setCellValue('S'.$row, (float) $c->interes_pendiente_periodo);
            $sheet->setCellValue('T'.$row, (float) $c->saldo_final_periodo);
            $row++;
        }

        $lastDataRow = $row - 1;

        if ($lastDataRow >= $headerRow + 1) {
            $sheet->getStyle('G'.($headerRow + 1).':I'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $sheet->getStyle('L'.($headerRow + 1).':N'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $sheet->getStyle('P'.($headerRow + 1).':T'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $sheet->getStyle('D'.($headerRow + 1).':F'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('J'.($headerRow + 1).':K'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('O'.($headerRow + 1).':O'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('0.0000');
            $sheet->setAutoFilter('A'.$headerRow.':T'.$lastDataRow);
        }

        $sheet->freezePane('A'.($headerRow + 1));

        $summaryStart = $lastDataRow + 2;
        $sheet->mergeCells('A'.$summaryStart.':H'.$summaryStart);
        $sheet->setCellValue('A'.$summaryStart, 'Concepto');
        $sheet->mergeCells('I'.$summaryStart.':T'.$summaryStart);
        $sheet->setCellValue('I'.$summaryStart, $etiquetaTotales);
        $sheet->getStyle('A'.$summaryStart.':T'.$summaryStart)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $flows = [
            'Depósitos trimestrales' => ['depositos', false],
            'Monto días adicionales' => ['adicionales', false],
            'Intereses devengados' => ['intereses', false],
            'Anticipos' => ['anticipos', true],
            'Intereses pagados' => ['pagados', true],
            'Neto del período' => ['neto', false],
            'Interés capitalizado (informativo)' => ['capitalizado', false],
        ];

        $r = $summaryStart + 1;
        foreach ($flows as $label => [$key, $negativo]) {
            $valor = (float) $totalesPeriodo[$key];
            if ($negativo) {
                $valor *= -1;
            }

            $sheet->mergeCells('A'.$r.':H'.$r);
            $sheet->setCellValue('A'.$r, $label);
            $sheet->mergeCells('I'.$r.':T'.$r);
            $sheet->setCellValue('I'.$r, $valor);
            $sheet->getStyle('A'.$r.':T'.$r)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
            ]);
            $sheet->getStyle('I'.$r.':T'.$r)->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $r++;
        }

        $stocks = [
            'Saldo inicial del período' => $saldoInicialRango,
            'Saldo final del período' => $saldoFinalRango,
            'Saldo al cierre ('.$hasta.')' => $saldoActual,
            'Interés pendiente al cierre' => $interesPendiente,
        ];

        foreach ($stocks as $label => $value) {
            $sheet->mergeCells('A'.$r.':H'.$r);
            $sheet->setCellValue('A'.$r, $label);
            $sheet->mergeCells('I'.$r.':T'.$r);
            $sheet->setCellValue('I'.$r, $value);
            $sheet->getStyle('A'.$r.':T'.$r)->getFont()->setBold(true);
            $sheet->getStyle('I'.$r)->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $r++;
        }

        $sheet->getStyle('A'.$headerRow.':T'.$r)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
        ]);

        foreach (['A' => 6, 'B' => 13, 'C' => 13, 'D' => 7, 'E' => 8, 'F' => 7, 'G' => 15, 'H' => 15, 'I' => 18, 'J' => 10, 'K' => 12, 'L' => 16, 'M' => 15, 'N' => 15, 'O' => 12, 'P' => 15, 'Q' => 15, 'R' => 15, 'S' => 15, 'T' => 15] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $payments = $spreadsheet->createSheet();
        $payments->setTitle('Pagos de intereses');
        $spreadsheet->setActiveSheetIndex(0);
        $payments->mergeCells('A1:F1');
        $payments->setCellValue('A1', 'Pagos de intereses — '.$empleado->nombre_completo);
        $payments->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $paymentHeaders = ['Fecha', 'Monto', 'Moneda', 'Tasa de cambio', 'Comprobante', 'Observación'];
        foreach ($paymentHeaders as $i => $header) {
            $payments->setCellValue(chr(65 + $i).'3', $header);
        }
        $payments->getStyle('A3:F3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $pr = 4;
        foreach ($pagos as $p) {
            $payments->setCellValue('A'.$pr, $p->fecha->format('d/m/Y'));
            $payments->setCellValue('B'.$pr, (float) $p->monto);
            $payments->setCellValue('C'.$pr, $p->moneda);
            $payments->setCellValue('D'.$pr, $p->tasa_cambio !== null ? (float) $p->tasa_cambio : null);
            $payments->setCellValue('E'.$pr, $p->comprobante);
            $payments->setCellValue('F'.$pr, $p->observacion);
            $pr++;
        }

        if ($pagos->isEmpty()) {
            $payments->mergeCells('A4:F4');
            $payments->setCellValue('A4', 'Sin pagos de intereses registrados.');
        } else {
            $payments->getStyle('B4:B'.($pr - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $payments->getStyle('D4:D'.($pr - 1))->getNumberFormat()->setFormatCode('0.0000');
        }

        $payments->getStyle('A3:F'.max(4, $pr - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
        ]);
        foreach (['A' => 12, 'B' => 16, 'C' => 10, 'D' => 14, 'E' => 24, 'F' => 40] as $col => $width) {
            $payments->getColumnDimension($col)->setWidth($width);
        }

        $sufijo = $hayRango ? '-'.$desde.'-a-'.$hasta : '';
        $filename = 'estado-cuenta-prestaciones-'.(Str::slug($empleado->nombre_completo) ?: 'empleado').$sufijo.'.ods';
        $writer = new Ods($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.oasis.opendocument.spreadsheet',
        ]);
    }

    /** PDF del estado de cuenta, con el mismo filtro de período. */
    public function pdf(Request $request, int $empleadoId)
    {
        $empleado = $this->empleadoDePatrono($empleadoId);
        $patrono = $this->patronoActual();
        $filtros = $this->filtrosPeriodo($request);
        // Sin filtro explícito, el PDF cierra en el último período guardado
        // (real o proyectado), igual que la pantalla.
        $hastaConsulta = $filtros['hasta'] ?? $this->service->ultimoPeriodoConProyeccion($empleado);
        $rango = $this->service->estadoDeCuentaEnRango($empleado, $filtros['desde'], $hastaConsulta);

        $incluirAnteriores = $filtros['incluir_anteriores'];
        $hayRango = $rango['hay_rango'];
        $desde = $rango['desde'];
        $hasta = $rango['hasta'];
        $cuenta = $this->service->filasConSaldosDelPeriodo(
            $rango['filas'],
            $hayRango && $incluirAnteriores
        );

        // Movimientos siempre del período; el checkbox solo agrega el saldo
        // acumulado anterior al saldo inicial.
        $totales = $rango['totales_periodo'];
        $netoPeriodo = $totales['neto'];
        $saldoInicialRango = ($hayRango && $incluirAnteriores)
            ? $rango['saldo_inicial']
            : 0.0;
        $saldoFinalRango = $this->saldoFinalDeRango($cuenta, $saldoInicialRango);
        $saldoActual = $saldoFinalRango;
        $interesPendiente = $rango['interes_pendiente'];
        $totalDepositos = $totales['depositos'];
        $totalAdicionales = $totales['adicionales'];
        $totalIntereses = $totales['intereses'];
        $totalInteresesCapitalizados = $totales['capitalizado'];
        $totalInteresesPagados = $totales['pagados'];
        $totalAnticipos = $totales['anticipos'];
        $periodoDesde = $desde;
        $proyeccionHasta = PrestacionSocial::where('empleado_id', $empleado->id)
            ->where('es_proyeccion', true)
            ->max('periodo');
        $incluyeProyeccion = $rango['filas']->contains(fn ($c) => (bool) $c->es_proyeccion);
        $periodosFiltrados = $rango['periodos_filtrados'];
        $totalPeriodos = $rango['total_periodos'];

        $sufijo = $hayRango ? '-'.$rango['desde'].'-a-'.$rango['hasta'] : '';

        return Pdf::loadView('prestaciones.pdf', [
            'empleado' => $empleado,
            'patrono' => $patrono,
            'cuenta' => $cuenta,
            'saldoActual' => $saldoActual,
            'saldoInicialRango' => $saldoInicialRango,
            'saldoFinalRango' => $saldoFinalRango,
            'interesPendiente' => $interesPendiente,
            'totalDepositos' => $totalDepositos,
            'totalAdicionales' => $totalAdicionales,
            'totalIntereses' => $totalIntereses,
            'totalInteresesCapitalizados' => $totalInteresesCapitalizados,
            'totalInteresesPagados' => $totalInteresesPagados,
            'totalAnticipos' => $totalAnticipos,
            'netoPeriodo' => $netoPeriodo,
            'incluirAnteriores' => $incluirAnteriores,
            'desde' => $desde,
            'hasta' => $hasta,
            'hayRango' => $hayRango,
            'periodosFiltrados' => $periodosFiltrados,
            'totalPeriodos' => $totalPeriodos,
            'periodoDesde' => $periodoDesde,
            'proyeccionHasta' => $proyeccionHasta,
            'incluyeProyeccion' => $incluyeProyeccion,
        ])->setPaper('letter', 'landscape')
            ->stream("estado-cuenta-prestaciones-{$empleado->nombre_completo}{$sufijo}.pdf");
    }
}
