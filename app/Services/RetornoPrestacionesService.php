<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Empleado;
use Illuminate\Http\Request;

class RetornoPrestacionesService
{
    /** @var list<int> */
    public const POR_PAGINA_OPCIONES = [6, 12, 18, 24, 30, 36, 42, 48, 54, 60];

    /**
     * Construye el regreso al estado de cuenta solo cuando la operación se
     * inició desde prestaciones y los parámetros son válidos.
     */
    public function url(Request $request, Empleado $empleado): ?string
    {
        if ($request->input('origen') !== 'prestaciones') {
            return null;
        }

        if ((int) $request->input('volver_empleado_id') !== (int) $empleado->id) {
            return null;
        }

        $parametros = ['empleadoId' => $empleado->id];

        $pagina = filter_var($request->input('volver_page'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($pagina !== false) {
            $parametros['page'] = (int) $pagina;
        }

        $porPagina = (int) $request->input('volver_porPagina');
        if (in_array($porPagina, self::POR_PAGINA_OPCIONES, true)) {
            $parametros['porPagina'] = $porPagina;
        }

        $desde = $this->periodo($request->input('volver_desde'));
        $hasta = $this->periodo($request->input('volver_hasta'));

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $desde = null;
            $hasta = null;
        }

        if ($desde !== null) {
            $parametros['desde'] = $desde;
        }
        if ($hasta !== null) {
            $parametros['hasta'] = $hasta;
        }

        $incluir = $request->input('volver_incluir_anteriores');
        if ($incluir === '0' || $incluir === '1' || $incluir === 0 || $incluir === 1) {
            $parametros['incluir_anteriores'] = (int) $incluir;
        }

        return route('prestaciones.show', $parametros);
    }

    private function periodo(mixed $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return null;
        }

        return $valor;
    }
}
