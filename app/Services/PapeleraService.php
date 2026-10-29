<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BonificacionFinAno;
use App\Models\CambioSalario;
use App\Models\Empleado;
use App\Models\HistorialEstado;
use App\Models\Liquidacion;
use App\Models\MovimientoPrestaciones;
use App\Models\PagoInteresPrestaciones;
use App\Models\Patrono;
use App\Models\PrestacionSocial;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\VacacionesRecibo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Define los tipos de registro que viven en la papelera y ejecuta su purga
 * definitiva, ya sea manual o por la retención automática.
 *
 * La purga usa `forceDelete()`. Antes de borrar se comprueban las dependencias
 * con clave foránea en cascada para no arrastrar registros que el usuario no
 * pidió eliminar.
 */
class PapeleraService
{
    public function __construct(
        private SueldosService $sueldos = new SueldosService,
        private PrestacionesSocialesService $prestaciones = new PrestacionesSocialesService,
    ) {}

    /**
     * Días que un registro permanece en la papelera antes de la purga automática.
     * Cero o menos desactiva la retención.
     */
    public function retencionDias(): int
    {
        return max(0, (int) config('papelera.retencion_dias', 90));
    }

    /**
     * Definición de los tipos restaurables y purgables.
     *
     * @return array<string, array{
     *     label:string,
     *     modelo:class-string<Model>,
     *     relaciones:list<string>,
     *     busqueda:list<string>,
     *     busqueda_relacion:array<string, list<string>>,
     *     titulo:callable(Model):string,
     *     detalle:callable(Model):?string,
     *     conflicto:callable(Model):?string,
     *     depende:callable(Model):?string,
     *     purgable:callable(Model):?string,
     *     despues:callable(Model):void
     * }>
     */
    public function tipos(): array
    {
        $sinConflicto = static fn (Model $registro): ?string => null;
        $sinDependencia = static fn (Model $registro): ?string => null;
        $sinBloqueo = static fn (Model $registro): ?string => null;
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
                'busqueda' => ['nombres', 'apellidos', 'cedula'],
                'busqueda_relacion' => [],
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
                'purgable' => static function (Empleado $registro): ?string {
                    if ($registro->tieneDocumentosAsociados()) {
                        return 'No se puede purgar: el trabajador aún tiene documentos o cálculos asociados, incluidos los eliminados.';
                    }

                    return null;
                },
                'despues' => $sinRecalculo,
            ],
            'patronos' => [
                'label' => 'Patronos',
                'modelo' => Patrono::class,
                'relaciones' => [],
                'busqueda' => ['razon_social', 'rif', 'nombre_comercial'],
                'busqueda_relacion' => [],
                'titulo' => static fn (Patrono $registro): string => "{$registro->razon_social} ({$registro->rif})",
                'detalle' => static fn (Patrono $registro): ?string => $registro->estado,
                'conflicto' => static function (Patrono $registro): ?string {
                    if (Patrono::where('rif', $registro->rif)->exists()) {
                        return 'No se puede restaurar: ya existe un patrono activo con ese RIF.';
                    }

                    return null;
                },
                'depende' => $sinDependencia,
                'purgable' => function (Patrono $registro): ?string {
                    if ($this->patronoTieneDependencias($registro)) {
                        return 'No se puede purgar: el patrono aún tiene trabajadores, documentos o usuarios asociados.';
                    }

                    return null;
                },
                'despues' => $sinRecalculo,
            ],
            'usuarios' => [
                'label' => 'Usuarios',
                'modelo' => User::class,
                'relaciones' => [],
                'busqueda' => ['name', 'email'],
                'busqueda_relacion' => [],
                'titulo' => static fn (User $registro): string => $registro->name,
                'detalle' => static fn (User $registro): ?string => $registro->email,
                'conflicto' => static function (User $registro): ?string {
                    if (User::where('email', $registro->email)->exists()) {
                        return 'No se puede restaurar: ya existe un usuario activo con ese correo.';
                    }

                    return null;
                },
                'depende' => $sinDependencia,
                'purgable' => $sinBloqueo,
                'despues' => $sinRecalculo,
            ],
            'vacaciones' => [
                'label' => 'Vacaciones',
                'modelo' => Vacacion::class,
                'relaciones' => ['empleado'],
                'busqueda' => [],
                'busqueda_relacion' => ['empleado' => ['nombres', 'apellidos', 'cedula']],
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
                'purgable' => static function (Vacacion $registro): ?string {
                    if (VacacionesRecibo::where('vacacion_id', $registro->getKey())->exists()) {
                        return 'No se puede purgar: la vacación todavía tiene recibos activos.';
                    }

                    return null;
                },
                'despues' => $sinRecalculo,
            ],
            'recibos-vacaciones' => [
                'label' => 'Recibos de vacaciones',
                'modelo' => VacacionesRecibo::class,
                'relaciones' => ['empleado'],
                'busqueda' => [],
                'busqueda_relacion' => ['empleado' => ['nombres', 'apellidos', 'cedula']],
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
                'purgable' => $sinBloqueo,
                'despues' => $sinRecalculo,
            ],
            'liquidaciones' => [
                'label' => 'Liquidaciones',
                'modelo' => Liquidacion::class,
                'relaciones' => ['empleado'],
                'busqueda' => ['motivo', 'observacion'],
                'busqueda_relacion' => ['empleado' => ['nombres', 'apellidos', 'cedula']],
                'titulo' => static fn (Liquidacion $registro): string => 'Liquidación #'.$registro->getKey().' — '.$registro->fecha_liquidacion?->format('d/m/Y'),
                'detalle' => static fn (Liquidacion $registro): ?string => $registro->empleado?->nombre_completo,
                'conflicto' => $sinConflicto,
                'depende' => $empleadoVivo,
                'purgable' => $sinBloqueo,
                'despues' => $sinRecalculo,
            ],
            'bonificaciones' => [
                'label' => 'Bonificaciones',
                'modelo' => BonificacionFinAno::class,
                'relaciones' => ['empleado'],
                'busqueda' => [],
                'busqueda_relacion' => ['empleado' => ['nombres', 'apellidos', 'cedula']],
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
                'purgable' => $sinBloqueo,
                'despues' => $sinRecalculo,
            ],
            'movimientos' => [
                'label' => 'Anticipos y préstamos',
                'modelo' => MovimientoPrestaciones::class,
                'relaciones' => ['empleado'],
                'busqueda' => ['concepto', 'motivo', 'observacion'],
                'busqueda_relacion' => ['empleado' => ['nombres', 'apellidos', 'cedula']],
                'titulo' => static fn (MovimientoPrestaciones $registro): string => $registro->labelTipo().' — '.$registro->fecha?->format('d/m/Y'),
                'detalle' => static fn (MovimientoPrestaciones $registro): ?string => $registro->empleado?->nombre_completo.' — '.$registro->moneda.' '.formato_monto((float) $registro->monto),
                'conflicto' => $sinConflicto,
                'depende' => $empleadoVivo,
                'purgable' => $sinBloqueo,
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
                'busqueda' => ['comprobante', 'observacion'],
                'busqueda_relacion' => ['empleado' => ['nombres', 'apellidos', 'cedula']],
                'titulo' => static fn (PagoInteresPrestaciones $registro): string => 'Pago — '.$registro->fecha?->format('d/m/Y'),
                'detalle' => static fn (PagoInteresPrestaciones $registro): ?string => $registro->empleado?->nombre_completo.' — '.$registro->moneda.' '.formato_monto((float) $registro->monto),
                'conflicto' => $sinConflicto,
                'depende' => $empleadoVivo,
                'purgable' => $sinBloqueo,
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
                'busqueda' => ['moneda'],
                'busqueda_relacion' => ['empleado' => ['nombres', 'apellidos', 'cedula']],
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
                'purgable' => $sinBloqueo,
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

    /**
     * Purgar un registro de la papelera. Devuelve el motivo del bloqueo o
     * `null` cuando se eliminó definitivamente.
     */
    public function purgar(string $tipo, Model $registro): ?string
    {
        $config = $this->config($tipo);

        if ($error = $config['purgable']($registro)) {
            return $error;
        }

        DB::transaction(function () use ($config, $registro, $tipo): void {
            $this->registrarPurgado($tipo, $registro, $config);
            $registro->forceDelete();
        });

        return null;
    }

    /**
     * Purga los registros que superaron la retención. Con `$simular` no borra
     * nada; solo informa lo que haría.
     *
     * @return array<string, array{
     *     label:string,
     *     purgados:int,
     *     bloqueados:list<array{id:int, motivo:string}>
     * }>
     */
    public function purgarExpirados(int $dias, ?string $tipo = null, bool $simular = false): array
    {
        if ($dias < 1) {
            return [];
        }

        $limite = now()->subDays($dias);
        $tipos = $tipo === null ? $this->tipos() : [$tipo => $this->config($tipo)];
        $resumen = [];

        foreach ($tipos as $clave => $config) {
            $clase = $config['modelo'];

            $registros = $clase::onlyTrashed()
                ->where('deleted_at', '<=', $limite)
                ->orderBy('id')
                ->get();

            $purgados = 0;
            $bloqueados = [];

            foreach ($registros as $registro) {
                if ($error = $config['purgable']($registro)) {
                    $bloqueados[] = ['id' => (int) $registro->getKey(), 'motivo' => $error];

                    continue;
                }

                if (! $simular) {
                    $this->purgar($clave, $registro);
                }

                $purgados++;
            }

            $resumen[$clave] = [
                'label' => $config['label'],
                'purgados' => $purgados,
                'bloqueados' => $bloqueados,
            ];
        }

        return $resumen;
    }

    /**
     * Configuración de un tipo concreto.
     *
     * @return array{
     *     label:string,
     *     modelo:class-string<Model>,
     *     relaciones:list<string>,
     *     busqueda:list<string>,
     *     busqueda_relacion:array<string, list<string>>,
     *     titulo:callable(Model):string,
     *     detalle:callable(Model):?string,
     *     conflicto:callable(Model):?string,
     *     depende:callable(Model):?string,
     *     purgable:callable(Model):?string,
     *     despues:callable(Model):void
     * }
     */
    public function config(string $tipo): array
    {
        $tipos = $this->tipos();

        if (! array_key_exists($tipo, $tipos)) {
            throw new \InvalidArgumentException("Tipo de papelera desconocido: {$tipo}");
        }

        return $tipos[$tipo];
    }

    /**
     * @param  array{titulo:callable(Model):string}  $config
     */
    private function registrarPurgado(string $tipo, Model $registro, array $config): void
    {
        HistorialEstado::create([
            'patrono_id' => $tipo === 'patronos' ? null : $registro->getAttribute('patrono_id'),
            'auditable_type' => $registro::class,
            'auditable_id' => $registro->getKey(),
            'campo' => 'papelera',
            'valor_anterior' => 'eliminado',
            'valor_nuevo' => 'purgado',
            'user_id' => Auth::id(),
            'nota' => 'Purgado definitivamente: '.$config['titulo']($registro),
        ]);
    }

    /**
     * Comprueba cualquier fila que la clave foránea en cascada borraría junto
     * al patrono, incluidas las que ya están en la papelera.
     */
    private function patronoTieneDependencias(Patrono $patrono): bool
    {
        $modelos = [
            Empleado::class,
            Vacacion::class,
            BonificacionFinAno::class,
            MovimientoPrestaciones::class,
            Liquidacion::class,
            PrestacionSocial::class,
            PagoInteresPrestaciones::class,
        ];

        foreach ($modelos as $modelo) {
            $query = $modelo::query();

            if (in_array(SoftDeletes::class, class_uses_recursive($modelo), true)) {
                $query->withTrashed();
            }

            if ($query->where('patrono_id', $patrono->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
