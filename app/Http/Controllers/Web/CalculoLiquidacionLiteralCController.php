<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculoLiquidacionLiteralCRequest;
use App\Models\CalculoLiquidacionLiteralC;
use App\Services\CalculoLiquidacionLiteralCService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CalculoLiquidacionLiteralCController extends Controller
{
    public function __construct(
        private CalculoLiquidacionLiteralCService $service = new CalculoLiquidacionLiteralCService,
    ) {}

    public function index(Request $request): View
    {
        $porPagina = (int) min(max($request->integer('porPagina', 15), 5), 100);

        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'moneda' => ['nullable', 'in:USD,BS'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $query = CalculoLiquidacionLiteralC::query()
            ->with(['creador', 'editor'])
            ->orderByDesc('fecha_egreso')
            ->orderByDesc('id');

        if ($buscar = trim((string) ($filtros['buscar'] ?? ''))) {
            $query->where(function ($q) use ($buscar) {
                $q->where('patrono_nombre', 'like', "%{$buscar}%")
                    ->orWhere('trabajador_nombre', 'like', "%{$buscar}%")
                    ->orWhere('trabajador_cedula', 'like', "%{$buscar}%");
            });
        }

        if (! empty($filtros['moneda'])) {
            $query->where('moneda', $filtros['moneda']);
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_egreso', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_egreso', '<=', $filtros['hasta']);
        }

        $calculos = $query->paginate($porPagina)->withQueryString();

        return view('calculos-literal-c.index', compact('calculos', 'porPagina', 'filtros'));
    }

    public function create(): View
    {
        Gate::authorize('gestionar');

        return view('calculos-literal-c.create');
    }

    public function store(CalculoLiquidacionLiteralCRequest $request): RedirectResponse
    {
        Gate::authorize('gestionar');

        $datos = $this->calcular($request->validated()) + [
            'mostrar_equivalencia' => $request->boolean('mostrar_equivalencia'),
        ];

        $calculo = CalculoLiquidacionLiteralC::create($datos + [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('calculos-literal-c.show', $calculo)
            ->with('success', 'Cálculo guardado correctamente.');
    }

    public function show(CalculoLiquidacionLiteralC $calculo): View
    {
        $calculo->load(['creador', 'editor']);
        $equivalencia = $calculo->mostrar_equivalencia ? $this->equivalencia($calculo) : null;

        return view('calculos-literal-c.show', compact('calculo', 'equivalencia'));
    }

    public function edit(CalculoLiquidacionLiteralC $calculo): View
    {
        Gate::authorize('gestionar');

        return view('calculos-literal-c.edit', compact('calculo'));
    }

    public function update(CalculoLiquidacionLiteralCRequest $request, CalculoLiquidacionLiteralC $calculo): RedirectResponse
    {
        Gate::authorize('gestionar');

        $calculo->update($this->calcular($request->validated()) + [
            'mostrar_equivalencia' => $request->boolean('mostrar_equivalencia'),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('calculos-literal-c.show', $calculo)
            ->with('success', 'Cálculo actualizado correctamente.');
    }

    public function duplicar(Request $request, CalculoLiquidacionLiteralC $calculo): RedirectResponse
    {
        Gate::authorize('gestionar');

        $copia = $calculo->replicate(['created_by', 'updated_by']);
        $copia->created_by = $request->user()?->id;
        $copia->updated_by = $request->user()?->id;
        $copia->save();

        return redirect()->route('calculos-literal-c.edit', $copia)
            ->with('success', 'Cálculo duplicado. Ajusta los datos y guarda los cambios.');
    }

    public function destroy(CalculoLiquidacionLiteralC $calculo): RedirectResponse
    {
        Gate::authorize('gestionar');

        $calculo->delete();

        return redirect()->route('calculos-literal-c.index')
            ->with('success', 'Cálculo eliminado definitivamente.');
    }

    public function pdf(CalculoLiquidacionLiteralC $calculo)
    {
        $calculo->load(['creador', 'editor']);
        $equivalencia = $calculo->mostrar_equivalencia ? $this->equivalencia($calculo) : null;

        return Pdf::loadView('calculos-literal-c.pdf', compact('calculo', 'equivalencia'))
            ->setPaper('letter', 'portrait')
            ->download($this->nombrePdf($calculo));
    }

    /** Nombre de descarga: Liq-142-c Patrono Trabajador ID.pdf */
    private function nombrePdf(CalculoLiquidacionLiteralC $calculo): string
    {
        $limpiar = function (string $valor): string {
            $valor = Str::ascii($valor);
            $valor = preg_replace('/[^A-Za-z0-9 _-]+/', '', $valor) ?? '';
            $valor = preg_replace('/\s+/', ' ', $valor) ?? '';

            return trim($valor);
        };

        $patrono = $limpiar((string) $calculo->patrono_nombre);
        $trabajador = $limpiar((string) $calculo->trabajador_nombre);

        return trim("Liq-142-c {$patrono} {$trabajador} {$calculo->id}").'.pdf';
    }

    /**
     * @return array{moneda:string, monto:float}|null
     */
    private function equivalencia(CalculoLiquidacionLiteralC $calculo): ?array
    {
        $tasa = (float) ($calculo->tasa_bcv ?? 0);

        if ($tasa <= 0) {
            return null;
        }

        return $calculo->moneda === CalculoLiquidacionLiteralC::MONEDA_USD
            ? ['moneda' => 'BS', 'monto' => round((float) $calculo->neto * $tasa, 2)]
            : ['moneda' => 'USD', 'monto' => round((float) $calculo->neto / $tasa, 2)];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function calcular(array $datos): array
    {
        $resultado = $this->service->calcular($datos);

        return [
            'patrono_nombre' => $datos['patrono_nombre'],
            'patrono_rif' => $datos['patrono_rif'] ?? null,
            'trabajador_nombre' => $datos['trabajador_nombre'],
            'trabajador_cedula' => $datos['trabajador_cedula'] ?? null,
            'cargo' => $datos['cargo'] ?? null,
            'fecha_ingreso' => $datos['fecha_ingreso'],
            'fecha_egreso' => $datos['fecha_egreso'],
            'tipo_salario' => $datos['tipo_salario'],
            'salario' => $datos['salario'],
            'moneda' => $datos['moneda'],
            'dias_utilidades' => $datos['dias_utilidades'],
            'anios_vacaciones_vencidas' => $datos['anios_vacaciones_vencidas'],
            'anios_utilidades_vencidas' => $datos['anios_utilidades_vencidas'],
            'salario_diario_normal' => $resultado['salario_diario_normal'],
            'salario_diario_integral' => $resultado['salario_diario_integral'],
            'tasa_bcv' => $resultado['tasa_bcv'],
            'anticipo' => $datos['anticipo'] ?? 0,
            'intereses_pagados' => $datos['intereses_pagados'] ?? 0,
            'prestamo_saldo' => $datos['prestamo_saldo'] ?? 0,
            'total_asignaciones' => $resultado['total_asignaciones'],
            'total_deducciones' => $resultado['total_deducciones'],
            'neto' => $resultado['neto'],
            'conceptos' => $resultado['conceptos'],
        ];
    }
}
