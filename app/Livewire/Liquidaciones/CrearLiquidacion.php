<?php

declare(strict_types=1);

namespace App\Livewire\Liquidaciones;

use App\Models\Empleado;
use App\Models\Patrono;
use App\Services\LiquidacionService;
use Illuminate\View\View;
use Livewire\Component;

class CrearLiquidacion extends Component
{
    public int $empleado_id = 0;
    public string $fecha_egreso = '';
    public string $motivo = 'renuncia';

    public ?array $preview = null;
    public ?string $error = null;

    public bool $guardando = false;

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

        $this->preview = $service->calcular(
            $empleado,
            \Carbon\Carbon::parse($egreso),
            $this->motivo
        );
    }

    public function guardar(): void
    {
        $this->guardando = true;
        $this->error = null;

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

        $carga = $this->preview ?? $service->calcular(
            $empleado,
            \Carbon\Carbon::parse($this->fecha_egreso ?: now()->toDateString()),
            $this->motivo
        );

        try {
            $service->crear(
                $empleado,
                \Carbon\Carbon::parse($this->fecha_egreso),
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
