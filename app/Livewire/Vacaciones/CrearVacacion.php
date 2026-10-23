<?php

declare(strict_types=1);

namespace App\Livewire\Vacaciones;

use App\Models\Empleado;
use App\Models\Patrono;
use App\Models\Vacacion;
use App\Services\DiasFeriadosService;
use App\Services\VacacionesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class CrearVacacion extends Component
{
    public int $empleado_id = 0;
    public string $fecha_inicio = '';

    public ?array $preview = null;
    public ?string $error = null;

    public bool $guardando = false;

    public function updatedEmpleadoId(): void
    {
        $this->preview = null;
        $this->error = null;
        $this->fecha_inicio = '';

        if ($this->empleado_id) {
            $this->calcular();
        }
    }

    public function updatedFechaInicio(): void
    {
        if ($this->empleado_id) {
            $this->calcular();
        }
    }

    public function calcular(): void
    {
        $this->preview = null;
        $this->error = null;

        $service = app(VacacionesService::class);
        $empleado = Empleado::with('patrono')->find($this->empleado_id);

        if (! $empleado || (int) $empleado->patrono_id !== (int) session('patrono_id')) {
            $this->error = 'Seleccione un trabajador del patrono activo.';

            return;
        }
        if (! $empleado->estaActivo()) {
            $this->error = 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.';

            return;
        }
        if (! $empleado->fecha_ingreso) {
            $this->error = 'El trabajador no tiene registrada la fecha de ingreso.';

            return;
        }
        if ((int) $service->aniosServicio($empleado) < 1) {
            $this->error = 'El trabajador debe haber completado al menos un año de servicio.';

            return;
        }

        // Fecha inicio sugerida: fin del periodo trabajado (día en que nace
        // el derecho a disfrutar las vacaciones).
        $pendiente = $service->proximoVacacionesPendiente($empleado);
        $sugerida = $pendiente['periodo_hasta']->format('Y-m-d');

        if (! $this->fecha_inicio) {
            $this->fecha_inicio = $sugerida;
        }

        if (! $this->fechaInicioValida($empleado)) {
            return;
        }

        $this->preview = $service->calcular($empleado, (int) $service->aniosServicio($empleado), $this->fecha_inicio ?: $sugerida);
    }

    public function guardar(): void
    {
        $this->guardando = true;
        $this->error = null;

        $service = app(VacacionesService::class);
        $empleado = Empleado::with('patrono')->find($this->empleado_id);

        if (! $empleado || (int) $empleado->patrono_id !== (int) session('patrono_id')) {
            $this->error = 'Seleccione un trabajador del patrono activo.';
            $this->guardando = false;

            return;
        }
        if (! $empleado->estaActivo()) {
            $this->error = 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos.';
            $this->guardando = false;

            return;
        }

        if ($this->fecha_inicio && ! $this->fechaInicioValida($empleado)) {
            $this->guardando = false;

            return;
        }

        $carga = $this->preview ?? $service->calcular(
            $empleado,
            (int) $service->aniosServicio($empleado),
            $this->fecha_inicio ?: null
        );

        $yaExiste = Vacacion::where('patrono_id', $empleado->patrono_id)
            ->where('empleado_id', $empleado->id)
            ->where('periodo_desde', $carga['periodo_desde'])
            ->where('periodo_hasta', $carga['periodo_hasta'])
            ->whereIn('estatus_vacacion', ['pendiente', 'pagada', 'disfrutada'])
            ->exists();

        if ($yaExiste) {
            $this->error = 'Ese periodo de vacaciones ya está registrado ('.
                \Carbon\Carbon::parse($carga['periodo_desde'])->format('d/m/Y').' — '.
                \Carbon\Carbon::parse($carga['periodo_hasta'])->format('d/m/Y').').';
            $this->guardando = false;

            return;
        }

        try {
            DB::transaction(function () use ($empleado, $carga) {
                Vacacion::create([
                    'patrono_id'                       => $empleado->patrono_id,
                    'empleado_id'                      => $empleado->id,
                    'fecha_calculo'                    => now()->toDateString(),
                    'fecha_inicio'                     => $carga['fecha_inicio_user'],
                    'fecha_inicio_real'                => $carga['fecha_inicio_real'],
                    'fecha_fin_real'                   => $carga['fecha_fin_real'],
                    'fecha_reintegro'                  => $carga['fecha_reintegro'],
                    'nro_vacacion'                     => $carga['anio_servicio'],
                    'periodo_desde'                    => $carga['periodo_desde'],
                    'periodo_hasta'                    => $carga['periodo_hasta'],
                    'dias_vacaciones'                  => (int) $carga['dias_vacaciones_base'],
                    'dias_adicionales'                 => (int) $carga['dias_vacaciones_adic'],
                    'dias_bono'                        => (int) $carga['dias_bono_base'],
                    'dias_bono_adicionales'            => (int) $carga['dias_bono_adic'],
                    'dias_descanso'                    => (int) $carga['dias_descanso'],
                    'dias_feriados'                    => (int) $carga['dias_feriados'],
                    'feriados_descanso_en_periodo'     => $carga['feriados_en_periodo'],
                    'salario_normal'                   => $carga['salario_normal'],
                    'moneda'                           => $carga['moneda'],
                    'estatus_calculo'                  => 'por_aprobar',
                    'estatus_vacacion'                 => 'pendiente',
                ]);
            });

            $this->reset(['empleado_id', 'fecha_inicio', 'preview']);
            $this->dispatch('vacacion-creada');
            $this->redirect(route('vacaciones.index'));
        } catch (\Illuminate\Database\QueryException $e) {
            $this->error = 'No se pudo guardar: ese periodo ya estaba registrado (dato duplicado).';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }

        $this->guardando = false;
    }

    /**
     * Valida que la fecha de inicio sea una fecha real, esté dentro del rango
     * de calendario admitido y no sea anterior al ingreso del trabajador.
     */
    private function fechaInicioValida(Empleado $empleado): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fecha_inicio)) {
            $this->error = 'La fecha de inicio debe tener el formato AAAA-MM-DD.';

            return false;
        }

        $anio = (int) substr($this->fecha_inicio, 0, 4);
        $mes = (int) substr($this->fecha_inicio, 5, 2);
        $dia = (int) substr($this->fecha_inicio, 8, 2);

        if (! checkdate($mes, $dia, $anio)) {
            $this->error = 'La fecha de inicio no es una fecha válida.';

            return false;
        }

        $inicio = Carbon::createFromFormat('Y-m-d', $this->fecha_inicio)->startOfDay();

        if ($anio < DiasFeriadosService::ANIO_MIN || $anio > DiasFeriadosService::ANIO_MAX) {
            $this->error = sprintf(
                'La fecha de inicio debe estar entre los años %d y %d.',
                DiasFeriadosService::ANIO_MIN,
                DiasFeriadosService::ANIO_MAX
            );

            return false;
        }

        if ($empleado->fecha_ingreso && $inicio->lt(Carbon::instance($empleado->fecha_ingreso))) {
            $this->error = 'La fecha de inicio no puede ser anterior a la fecha de ingreso del trabajador.';

            return false;
        }

        return true;
    }

    public function render(): View
    {
        $empleados = Patrono::find((int) session('patrono_id'))?->empleados()
            ->where('estatus', 'Activo')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cedula']) ?? collect();

        return view('livewire.vacaciones.crear-vacacion', compact('empleados'));
    }
}
