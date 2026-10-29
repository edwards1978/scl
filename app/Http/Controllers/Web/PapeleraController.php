<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BonificacionFinAno;
use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\VacacionesRecibo;
use App\Services\PrestacionesSocialesService;
use App\Services\SueldosService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Papelera de registros con borrado lógico.
 *
 * Solo los administradores pueden verla y restaurar. Cada tipo define cómo se
 * muestra, qué conflicto de clave única impide restaurarlo y qué recálculo hay
 * que ejecutar después de recuperarlo.
 */
class PapeleraController extends Controller
{
    public function __construct(
        private SueldosService $sueldos = new SueldosService,
        private PrestacionesSocialesService $prestaciones = new PrestacionesSocialesService,
    ) {}

    public function index(Request $request): View
    {
        $tipos = $this->tipos();
        $tipoActivo = (string) $request->query('tipo', 'empleados');

        if (! array_key_exists($tipoActivo, $tipos)) {
            $tipoActivo = 'empleados';
        }

        $config = $tipos[$tipoActivo];
        $modelo = $config['modelo'];

        $registros = $modelo::onlyTrashed()
            ->with($config['relaciones'])
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        $conteos = [];

        foreach ($tipos as $clave => $tipo) {
            $clase = $tipo['modelo'];
            $conteos[$clave] = $clase::onlyTrashed()->count();
        }

        return view('papelera.index', compact('tipos', 'tipoActivo', 'config', 'registros', 'conteos'));
    }

    public function restaurar(string $tipo, int $id): RedirectResponse
    {
        Gate::authorize('administrar');

        $tipos = $this->tipos();

        abort_unless(array_key_exists($tipo, $tipos), 404);

        $config = $tipos[$tipo];
        $clase = $config['modelo'];

        /** @var Model $registro */
        $registro = $clase::onlyTrashed()->findOrFail($id);

        if ($error = $config['conflicto']($registro)) {
            return back()->with('error', $error);
        }

        if ($error = $config['depende']($registro)) {
            return back()->with('error', $error);
        }

        DB::transaction(function () use ($registro, $config, $tipo): void {
            $registro->restore();

            if ($tipo === 'vacaciones') {
                VacacionesRecibo::onlyTrashed()
                    ->where('vacacion_id', $registro->getKey())
                    ->restore();
            }

            $config['despues']($registro);
        });

        return redirect()->route('papelera.index', ['tipo' => $tipo])
            ->with('success', 'Registro restaurado correctamente.');
    }

    /**
     * Definición de los tipos restaurables.
     *
     * @return array<string, array{
     *     label:string,
     *     modelo:class-string<Model>,
     *     relaciones:list<string>,
     *     titulo:callable(Model):string,
     *     detalle:callable(Model):?string,
     *     conflicto:callable(Model):?string,
     *     depende:callable(Model):?string,
     *     despues:callable(Model):void
     * }>
     */
    private function tipos(): array
    {
        $sinConflicto = static fn (Model $registro): ?string => null;
        $sinDependencia = static fn (Model $registro): ?string => null;
        $sinRecalculo = static function (Model $registro): void {};

        $empleadoVivo = static function (Model $registro): ?string {
            if (! Empleado::whereKey($registro->empleado_id)->exists()) {
                return 'Restaure primero al trabajador asociado a este registro.';
            }

            return null;
        };

        return [
            'empleados' => [
                'label' => 'Empleados',
                'modelo' => Empleado::class,
                'relaciones' => ['patrono'],
                'titulo' => static fn (Empleado $registro): string => "{$registro->nombre_completo} ({$registro->cedula})",
                'detalle' => static fn (Empleado $registro): ?string => $registro->patrono?->razon_social,
                'conflicto' => static function (Empleado $registro): ?string {
                    if (Empleado::where('patrono_id', $registro->patrono_id)->where('cedula', $registro->cedula)->exists()) {
                        return 'No se puede restaurar: ya existe un empleado activo con esa cédula en el patrono.';
                    }

                    return null;
                },
                'depende' => static function (Empleado $registro): ?string {
                    if (! Patrono::whereKey($registro->patrono_id)->exists()) {
                        return 'Restaure primero al patrono asociado a este trabajador.';
                    }

                    return null;
                },
                'despues' => $sinRecalculo,
            ],
            'patronos' => [
                'label' => 'Patronos',
                'modelo' => Patrono::class,
                'relaciones' => [],
                'titulo' => static fn (Patrono $registro): string => "{$registro->razon_social} ({$registro->rif})",
                'detalle' => static fn (Patrono $registro): ?string => $registro->estado,
                'conflicto' => static function (Patrono $registro): ?string {
                    if (Patrono::where('rif', $registro->rif)->exists()) {
                        return 'No se puede restaurar: ya existe un patrono activo con ese RIF.';
                    }

                    return null;
                },
                'depende' => $sinDependencia,
                'despues' => $sinRecalculo,
            ],
            'usuarios' => [
                'label' => 'Usuarios',
                'modelo' => User::class,
                'relaciones' => [],
                'titulo' => static fn (User $registro): string => $registro->name,
                'detalle' => static fn (User $registro): ?string => $registro->email,
                'conflicto' => static function (User $registro): ?string {
                    if (User::where('email', $registro->email)->exists()) {
                        return 'No se puede restaurar: ya existe un usuario activo con ese correo.';
                    }

                    return null;
                },
                'depende' => $sinDependencia,
                'despues' => $sinRecalculo,
            ],
            'vacaciones' => [
                'label' => 'Vacaciones',
                'modelo' => Vacacion::class,
                'relaciones' => ['empleado'],
                'titulo' => static fn (Vacacion $registro): string => 'Periodo '.$registro->periodo_desde?->format('d/m/Y').' — '.$registro->periodo_hasta?->format('d/m/Y'),
                'detalle' => static fn (Vacacion $registro): ?string => $registro->empleado?->nombre_completo,
                'conflicto' => static function (Vacacion $registro): ?string {
                    $existe = Vacacion::where('patrono_id', $registro->patrono_id)
                        ->where('empleado_id', $registro->empleado_id)
                        ->where('periodo_desde', $registro->periodo_desde)
                        ->where('periodo_hasta', $registro->periodo_hasta)
                        ->exists();

                    return $existe
                        ? 'No se puede restaurar: ya existe un periodo de vacaciones activo con esas fechas.'
                        : null;
                },
                'depende' => $empleadoVivo,
                'despues' => $sinRecalculo,
            ],
            'recibos-vacaciones' => [
                'label' => 'Recibos de vacaciones',
                'modelo' => VacacionesRecibo::class,
                'relaciones' => ['empleado'],
                'titulo' => static fn (VacacionesRecibo $registro): string => 'Recibo #'.$registro->getKey(),
                'detalle' => static fn (VacacionesRecibo $registro): ?string => $registro->empleado?->nombre_completo
                    .($registro->fecha_emision ? ' — '.$registro->fecha_emision->format('d/m/Y') : ''),
                'conflicto' => $sinConflicto,
                'depende' => static function (VacacionesRecibo $registro): ?string {
                    if (! Vacacion::whereKey($registro->vacacion_id)->exists()) {
                        return 'Restaure primero la vacación asociada a este recibo.';
                    }

                    return null;
                },
                'despues' => $sinRecalculo,
            ],
            'liquidaciones' => [
                'label' => 'Liquidaciones',
                'modelo' => Liquidacion::class,
                'relaciones' => ['empleado'],
                'titulo' => static fn (Liquidacion $registro): string => 'Liquidación #'.$registro->getKey().' — '.$registro->fecha_liquidacion?->format('d/m/Y'),
                'detalle' => static fn (Liquidacion $registro): ?string => $registro->empleado?->nombre_completo,
                'conflicto' => $sinConflicto,
                'depende' => $empleadoVivo,
                'despues' => $sinRecalculo,
            ],
            'bonificaciones' => [
                'label' => 'Bonificaciones',
                'modelo' => BonificacionFinAno::class,
                'relaciones' => ['empleado'],
                'titulo' => static fn (BonificacionFinAno $registro): string => 'Bonificación '.$registro->anio,
                'detalle' => static fn (BonificacionFinAno $registro): ?string => $registro->empleado?->nombre_completo,
                'conflicto' => static function (BonificacionFinAno $registro): ?string {
                    $existe = BonificacionFinAno::where('empleado_id', $registro->empleado_id)
                        ->where('anio', $registro->anio)
                        ->exists();

                    return $existe
                        ? 'No se puede restaurar: ya existe una bonificación activa para ese trabajador y año.'
                        : null;
                },
                'depende' => $empleadoVivo,
                'despues' => $sinRecalculo,
            ],
            'movimientos' => [
                'label' => 'Anticipos y préstamos',
                'modelo' => MovimientoPrestaciones::class,
                'relaciones' => ['empleado'],
                'titulo' => static fn (MovimientoPrestaciones $registro): string => $registro->labelTipo().' — '.$registro->fecha?->format('d/m/Y'),
                'detalle' => static fn (MovimientoPrestaciones $registro): ?string => $registro->empleado?->nombre_completo.' — '.$registro->moneda.' '.formato_monto((float) $registro->monto),
                'conflicto' => $sinConflicto,
                'depende' => $empleadoVivo,
                'despues' => function (MovimientoPrestaciones $registro): void {
                    if (! $registro->esAnticipo()) {
                        return;
                    }

                    $empleado = Empleado::find($registro->empleado_id);

                    if (! $empleado) {
                        return;
                    }

                    $periodo = $this->prestaciones->periodoDeFecha($empleado, $registro->fecha)
                        ?? $registro->fecha->format('Y-m');
                    $this->prestaciones->recalcularDesde($empleado, $periodo);
                },
            ],
            'pagos-intereses' => [
                'label' => 'Pagos de intereses',
                'modelo' => PagoInteresPrestaciones::class,
                'relaciones' => ['empleado'],
                'titulo' => static fn (PagoInteresPrestaciones $registro): string => 'Pago — '.$registro->fecha?->format('d/m/Y'),
                'detalle' => static fn (PagoInteresPrestaciones $registro): ?string => $registro->empleado?->nombre_completo.' — '.$registro->moneda.' '.formato_monto((float) $registro->monto),
                'conflicto' => $sinConflicto,
                'depende' => $empleadoVivo,
                'despues' => function (PagoInteresPrestaciones $registro): void {
                    $empleado = Empleado::find($registro->empleado_id);

                    if (! $empleado) {
                        return;
                    }

                    $periodo = $this->prestaciones->periodoDeFecha($empleado, $registro->fecha)
                        ?? $registro->fecha->format('Y-m');
                    $this->prestaciones->recalcularDesde($empleado, $periodo);
                },
            ],
            'cambios-salarios' => [
                'label' => 'Cambios salariales',
                'modelo' => CambioSalario::class,
                'relaciones' => ['empleado'],
                'titulo' => static fn (CambioSalario $registro): string => 'Cambio — '.$registro->fecha_cambio?->format('d/m/Y'),
                'detalle' => static fn (CambioSalario $registro): ?string => $registro->empleado?->nombre_completo.' — '.$registro->moneda.' '.formato_monto((float) $registro->monto),
                'conflicto' => static function (CambioSalario $registro): ?string {
                    $existe = CambioSalario::where('empleado_id', $registro->empleado_id)
                        ->where('fecha_cambio', $registro->fecha_cambio)
                        ->exists();

                    return $existe
                        ? 'No se puede restaurar: ya existe un cambio salarial activo para esa fecha.'
                        : null;
                },
                'depende' => $empleadoVivo,
                'despues' => function (CambioSalario $registro): void {
                    $empleado = Empleado::find($registro->empleado_id);

                    if (! $empleado) {
                        return;
                    }

                    $periodo = Carbon::parse($registro->fecha_cambio)->format('Y-m');

                    $this->sueldos->sincronizarFicha($empleado);
                    $this->sueldos->regenerarSueldos($empleado, $periodo);

                    if ($empleado->prestacionesSociales()->exists()) {
                        $this->prestaciones->regenerar($empleado, $periodo);
                    }
                },
            ],
        ];
    }
}
