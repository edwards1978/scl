<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use App\Services\PrestacionesSocialesService;
use App\Services\TasasBcvService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        $totalInteresesCapitalizados = (float) $cuenta->sum('interes_capitalizado');
        $totalInteresesPagados = (float) $cuenta->sum('interes_pagado');
        $interesPendiente = (float) ($cuenta->last()->interes_pendiente_final ?? 0.0);
        $totalAnticipos = (float) $cuenta->sum('anticipos');

        $pagos = PagoInteresPrestaciones::where('empleado_id', $empleado->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return view('prestaciones.show', compact(
            'empleado', 'cuenta', 'saldoActual',
            'totalDepositos', 'totalAdicionales', 'totalIntereses', 'totalAnticipos',
            'totalInteresesCapitalizados', 'totalInteresesPagados', 'interesPendiente', 'pagos'
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

    /** Registra un pago de intereses y recalcula desde el mes del pago. */
    public function pagarInteres(Request $request, int $empleadoId): RedirectResponse
    {
        $empleado = $this->empleadoDePatrono($empleadoId);

        $request->merge([
            'monto' => (string) parsear_numero((string) $request->input('monto')),
        ]);

        $data = $request->validate([
            'fecha'       => ['required', 'date', 'before_or_equal:today'],
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'moneda'      => ['required', 'in:USD,BS'],
            'comprobante' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $fecha = Carbon::parse($data['fecha']);
        $tasa = $data['moneda'] === 'USD'
            ? (new TasasBcvService)->tasaEn($fecha)
            : null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($empleado, $data, $fecha, $tasa) {
            PagoInteresPrestaciones::create([
                'patrono_id'  => $empleado->patrono_id,
                'empleado_id' => $empleado->id,
                'fecha'       => $fecha->toDateString(),
                'monto'       => $data['monto'],
                'moneda'      => $data['moneda'],
                'tasa_cambio' => $tasa,
                'comprobante' => $data['comprobante'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $this->service->regenerar($empleado, $fecha->format('Y-m'));
        });

        return redirect()->route('prestaciones.show', $empleado->id)
            ->with('success', 'Pago de intereses registrado y estado de cuenta recalculado.');
    }

    /** Elimina un pago de intereses mal registrado y recalcula. */
    public function destroyInteres(Request $request, int $empleadoId, PagoInteresPrestaciones $pago): RedirectResponse
    {
        $empleado = $this->empleadoDePatrono($empleadoId);

        abort_if((int) $pago->empleado_id !== (int) $empleado->id
            || (int) $pago->patrono_id !== (int) $empleado->patrono_id, 404);

        \Illuminate\Support\Facades\DB::transaction(function () use ($empleado, $pago) {
            $periodo = $pago->fecha->format('Y-m');
            $pago->delete();
            $this->service->regenerar($empleado, $periodo);
        });

        return redirect()->route('prestaciones.show', $empleado->id)
            ->with('success', 'Pago de intereses eliminado y estado de cuenta recalculado.');
    }

    /** Exporta el estado de cuenta a Excel (.xlsx). */
    public function excel(Request $request, int $empleadoId)
    {
        $empleado = $this->empleadoActivo($empleadoId);
        $patrono = $this->patronoActual();
        $cuenta = $this->service->estadoDeCuenta($empleado);
        $pagos = PagoInteresPrestaciones::where('empleado_id', $empleado->id)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $moneda = $empleado->moneda;
        $ultimo = $cuenta->last();
        $saldoActual = (float) ($ultimo->saldo_final ?? 0);
        $interesPendiente = (float) ($ultimo->interes_pendiente_final ?? 0);
        $totalDepositos = (float) $cuenta->sum('deposito_trimestre');
        $totalAdicionales = (float) $cuenta->sum('dias_adicionales');
        $totalIntereses = (float) $cuenta->sum('interes_mes');
        $totalCapitalizado = (float) $cuenta->sum('interes_capitalizado');
        $totalPagado = (float) $cuenta->sum('interes_pagado');
        $totalAnticipos = (float) $cuenta->sum('anticipos');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('SCL')
            ->setTitle('Estado de cuenta de prestaciones')
            ->setSubject('Prestaciones sociales');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Estado de cuenta');

        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', $patrono->razon_social);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->mergeCells('A2:P2');
        $sheet->setCellValue('A2', 'RIF ('.$patrono->rif.')');
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->mergeCells('A3:P3');
        $sheet->setCellValue('A3', 'Estado de Cuenta de Prestaciones Sociales — Arts. 142 y 143 LOTTT');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

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
        $sheet->mergeCells('N5:P5');
        $sheet->setCellValue('N5', $moneda);

        $sheet->setCellValue('A6', 'Emitido');
        $sheet->mergeCells('B6:D6');
        $sheet->setCellValue('B6', now()->format('d/m/Y H:i'));
        $sheet->setCellValue('E6', 'Saldo actual');
        $sheet->mergeCells('F6:H6');
        $sheet->setCellValue('F6', $saldoActual);
        $sheet->setCellValue('I6', 'Interés pendiente');
        $sheet->mergeCells('J6:L6');
        $sheet->setCellValue('J6', $interesPendiente);
        $sheet->getStyle('F6')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('J6')->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (['A5', 'E5', 'I5', 'M5', 'A6', 'E6', 'I6'] as $celda) {
            $sheet->getStyle($celda)->getFont()->setBold(true);
        }

        $headers = [
            'Nº', 'Periodo', 'Salario integral', 'Saldo inicial', 'Capital',
            'Depósito trimestral', 'Días depósito', 'Días adicionales',
            'Monto días adicionales', 'Interés del mes', 'Interés capitalizado',
            'Interés pagado', 'Interés pendiente', 'Anticipos', 'Saldo final', 'Tasa interés %',
        ];

        $headerRow = 9;
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(chr(65 + $i).$headerRow, $header);
        }

        $sheet->getStyle('A'.$headerRow.':P'.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        $row = $headerRow + 1;
        $numero = 0;
        foreach ($cuenta as $c) {
            $sheet->setCellValue('A'.$row, $numero++);
            $sheet->setCellValue('B'.$row, Carbon::createFromFormat('Y-m', $c->periodo)->format('m/Y'));
            $sheet->setCellValue('C'.$row, (float) $c->salario_integral_diario);
            $sheet->setCellValue('D'.$row, (float) $c->saldo_inicial);
            $sheet->setCellValue('E'.$row, (float) ($c->capital_final ?? $c->saldo_final));
            $sheet->setCellValue('F'.$row, (float) $c->deposito_trimestre);
            $sheet->setCellValue('G'.$row, (int) $c->dias_deposito);
            $sheet->setCellValue('H'.$row, (int) $c->dias_adicionales_cont);
            $sheet->setCellValue('I'.$row, (float) $c->dias_adicionales);
            $sheet->setCellValue('J'.$row, (float) $c->interes_mes);
            $sheet->setCellValue('K'.$row, (float) ($c->interes_capitalizado ?? 0));
            $sheet->setCellValue('L'.$row, (float) ($c->interes_pagado ?? 0));
            $sheet->setCellValue('M'.$row, (float) ($c->interes_pendiente_final ?? 0));
            $sheet->setCellValue('N'.$row, -1 * (float) $c->anticipos);
            $sheet->setCellValue('O'.$row, (float) $c->saldo_final);
            $sheet->setCellValue('P'.$row, $c->tasa_interes !== null ? (float) $c->tasa_interes : null);
            $row++;
        }

        $lastDataRow = $row - 1;

        if ($lastDataRow >= $headerRow + 1) {
            $sheet->getStyle('C'.($headerRow + 1).':O'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $sheet->getStyle('G'.($headerRow + 1).':H'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('P'.($headerRow + 1).':P'.$lastDataRow)
                ->getNumberFormat()->setFormatCode('0.0000');
            $sheet->setAutoFilter('A'.$headerRow.':P'.$lastDataRow);
        }

        $sheet->freezePane('A'.($headerRow + 1));

        $summaryStart = $lastDataRow + 2;
        $summary = [
            'Total depósitos trimestrales' => $totalDepositos,
            'Total monto días adicionales' => $totalAdicionales,
            'Total intereses devengados' => $totalIntereses,
            'Total interés capitalizado' => $totalCapitalizado,
            'Total interés pagado' => $totalPagado,
            'Interés pendiente' => $interesPendiente,
            'Total anticipos' => $totalAnticipos,
            'Saldo actual' => $saldoActual,
        ];

        $r = $summaryStart;
        foreach ($summary as $label => $value) {
            $sheet->mergeCells('A'.$r.':H'.$r);
            $sheet->setCellValue('A'.$r, $label);
            $sheet->setCellValue('I'.$r, $value);
            $sheet->getStyle('A'.$r.':I'.$r)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
            ]);
            $sheet->getStyle('I'.$r)->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
            $r++;
        }

        $sheet->getStyle('A'.$headerRow.':P'.$r)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
        ]);

        foreach (['A' => 6, 'B' => 10, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 18, 'G' => 10, 'H' => 12, 'I' => 16, 'J' => 15, 'K' => 15, 'L' => 15, 'M' => 15, 'N' => 15, 'O' => 15, 'P' => 12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $payments = $spreadsheet->createSheet();
        $payments->setTitle('Pagos de intereses');
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

        $filename = 'estado-cuenta-prestaciones-'.(Str::slug($empleado->nombre_completo) ?: 'empleado').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
