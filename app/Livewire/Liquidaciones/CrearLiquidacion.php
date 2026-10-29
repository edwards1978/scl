<?php

declare(strict_types=1);

namespace App\Livewire\Liquidaciones;

use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\Patrono;
use App\Services\DiasFeriadosService;
use App\Services\LiquidacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class CrearLiquidacion extends Component
{
    public int $empleado_id = 0;

    public string $fecha_egreso = '';

    public string $motivo = 'retiro_voluntario';

    public ?array $preview = null;

    public ?string $error = null;

    public bool $guardando = false;

    public function mount(): void
    {
        Gate::authorize('gestionar');
    }

    public function updatedEmpleadoId(): void
    {
        $this->preview = null;
        $this->error = null;

        if ($this->empleado_id) {
            $this->calcular();
        }
    }

    public function updatedFechaEgreso(): void
    {
        $this->calcular();
    }

    public function updatedMotivo(): void
    {
        $this->calcular();
    }

    public function calcular(): void
    {
        $this->preview = null;
        $this->error = null;

        if (! $this->motivoValido()) {
            return;
        }

        $service = app(LiquidacionService::class);
        $empleado = Empleado::with('patrono')->find($this->empleado_id);

        if (! $empleado || (int) $empleado->patrono_id !== (int) session('patrono_id')) {
            $this->error = 'Seleccione un trabajador del patrono activo.';

            return;
        }
        if (! $empleado->estaActivo()) {
            $this->error = 'El trabajador está inactivo (ya liquidado): no se pueden efectuar nuevos cálculos ni una nueva liquidación.';

            return;
        }
        if (! $empleado->fecha_ingreso) {
            $this->error = 'El trabajador no tiene registrada la fecha de ingreso.';

            return;
        }

        $egreso = $this->fecha_egreso ?: now()->toDateString();
        $this->fecha_egreso = $egreso;

        if (! $this->fechaEgresoValida($empleado)) {
            return;
        }

        $this->preview = $service->calcular(
            $empleado,
            Carbon::parse($egreso),
            $this->motivo
        );
    }

    public function guardar(): void
    {
        Gate::authorize('gestionar');

        $this->guardando = true;
        $this->error = null;

        if (! $this->motivoValido()) {
            $this->guardando = false;

            return;
        }

        $service = app(LiquidacionService::class);
        $empleado = Empleado::with('patrono')->find($this->empleado_id);

        if (! $empleado || (int) $empleado->patrono_id !== (int) session('patrono_id')) {
            $this->error = 'Seleccione un trabajador del patrono activo.';
            $this->guardando = false;

            return;
        }
        if (! $empleado->estaActivo()) {
            $this->error = 'El trabajador está inactivo (ya liquidado): no se puede crear una nueva liquidación.';
            $this->guardando = false;

            return;
        }

        $this->fecha_egreso = $this->fecha_egreso ?: now()->toDateString();

        if (! $this->fechaEgresoValida($empleado)) {
            $this->guardando = false;

            return;
        }

        $carga = $this->preview ?? $service->calcular(
            $empleado,
            Carbon::parse($this->fecha_egreso ?: now()->toDateString()),
            $this->motivo
        );

        try {
            $service->crear(
                $empleado,
                Carbon::parse($this->fecha_egreso),
                $this->motivo
            );

            $this->reset(['empleado_id', 'fecha_egreso', 'preview']);
            $this->dispatch('liquidacion-creada');
            $this->redirect(route('liquidaciones.index'));
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }

        $this->guardando = false;
    }

    /**
     * Valida que el motivo pertenezca a los motivos de terminación admitidos.
     */
    private function motivoValido(): bool
    {
        if (! in_array($this->motivo, Liquidacion::MOTIVOS, true)) {
            $this->error = 'El motivo de terminación seleccionado no es válido.';

            return false;
        }

        return true;
    }

    /**
     * Valida que la fecha de egreso sea una fecha real, esté dentro del rango
     * de calendario admitido y no sea anterior al ingreso del trabajador.
     */
    private function fechaEgresoValida(Empleado $empleado): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->fecha_egreso)) {
            $this->error = 'La fecha de egreso debe tener el formato AAAA-MM-DD.';

            return false;
        }

        $anio = (int) substr($this->fecha_egreso, 0, 4);
        $mes = (int) substr($this->fecha_egreso, 5, 2);
        $dia = (int) substr($this->fecha_egreso, 8, 2);

        if (! checkdate($mes, $dia, $anio)) {
            $this->error = 'La fecha de egreso no es una fecha válida.';

            return false;
        }

        $egreso = Carbon::createFromFormat('Y-m-d', $this->fecha_egreso)->startOfDay();

        if ($anio < DiasFeriadosService::ANIO_MIN || $anio > DiasFeriadosService::ANIO_MAX) {
            $this->error = sprintf(
                'La fecha de egreso debe estar entre los años %d y %d.',
                DiasFeriadosService::ANIO_MIN,
                DiasFeriadosService::ANIO_MAX
            );

            return false;
        }

        if ($empleado->fecha_ingreso && $egreso->lt(Carbon::instance($empleado->fecha_ingreso))) {
            $this->error = 'La fecha de egreso no puede ser anterior a la fecha de ingreso del trabajador.';

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

        return view('livewire.liquidaciones.crear-liquidacion', compact('empleados'));
    }
}
