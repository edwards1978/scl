<?php

declare(strict_types=1);

namespace App\Livewire\Entidades;

use App\Models\Empleado;
use App\Models\Patrono;
use App\Rules\CedulaVenezolanaRule;
use App\Services\SueldosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class CrearEmpleado extends Component
{
    public string $nombres = '';

    public string $apellidos = '';

    public string $cedula = '';

    public string $fecha_nacimiento = '';

    public string $genero = '';

    public string $telefono = '';

    public string $correo = '';

    public string $direccion = '';

    public string $fecha_ingreso = '';

    public string $cargo = '';

    public string $departamento = '';

    public string $tipo_contrato = 'Indeterminado';

    public string $tipo_salario = 'Fijo';

    public string $jornada_laboral = '';

    public string $salario_base = '0';

    public string $moneda = 'BS';

    public string $estatus = 'Activo';

    public bool $capitaliza_intereses = false;

    public string $capitalizacion_autorizada_desde = '';

    public string $capitalizacion_documento = '';

    public bool $guardando = false;

    public ?string $exito = null;

    public function mount(): void
    {
        Gate::authorize('gestionar');

        $this->fecha_ingreso = date('Y-m-d');
    }

    public function guardar(): void
    {
        Gate::authorize('gestionar');

        $this->guardando = true;
        $this->exito = null;

        $this->salario_base = (string) parsear_numero($this->salario_base);

        $this->validate();

        DB::transaction(function (): void {
            $empleado = Empleado::create([
                'patrono_id' => (int) session('patrono_id'),
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'cedula' => $this->cedula,
                'fecha_nacimiento' => $this->fecha_nacimiento ?: null,
                'genero' => $this->genero ?: null,
                'telefono' => $this->telefono ?: null,
                'correo' => $this->correo ?: null,
                'direccion' => $this->direccion ?: null,
                'fecha_ingreso' => $this->fecha_ingreso,
                'cargo' => $this->cargo ?: null,
                'departamento' => $this->departamento ?: null,
                'tipo_contrato' => $this->tipo_contrato,
                'tipo_salario' => $this->tipo_salario,
                'jornada_laboral' => $this->jornada_laboral ?: null,
                'salario_base' => (float) $this->salario_base,
                'salario_inicial' => (float) $this->salario_base,
                'moneda' => $this->moneda,
                'estatus' => $this->estatus,
                'capitaliza_intereses' => $this->capitaliza_intereses,
                'capitalizacion_autorizada_desde' => $this->capitaliza_intereses ? ($this->capitalizacion_autorizada_desde ?: null) : null,
                'capitalizacion_documento' => $this->capitaliza_intereses ? ($this->capitalizacion_documento ?: null) : null,
            ]);

            app(SueldosService::class)->registrarIngreso($empleado);
        });

        $this->dispatch('empleado-creado');
        $this->exito = 'Empleado creado correctamente.';
        $this->reset(['nombres', 'apellidos', 'cedula', 'fecha_nacimiento',
            'genero', 'telefono', 'correo', 'direccion', 'cargo', 'departamento',
            'tipo_contrato', 'tipo_salario', 'jornada_laboral', 'salario_base', 'moneda',
            'capitaliza_intereses', 'capitalizacion_autorizada_desde', 'capitalizacion_documento']);
        $this->tipo_contrato = 'Indeterminado';
        $this->tipo_salario = 'Fijo';
        $this->moneda = 'BS';
        $this->estatus = 'Activo';
        $this->fecha_ingreso = date('Y-m-d');
        $this->guardando = false;
    }

    protected function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'cedula' => ['required', 'string', 'max:15', new CedulaVenezolanaRule],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'genero' => ['nullable', 'in:M,F'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string'],
            'fecha_ingreso' => ['required', 'date'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'tipo_contrato' => ['required', 'in:Determinado,Indeterminado,Obra'],
            'tipo_salario' => ['required', 'in:Fijo,Variable,Mixto'],
            'jornada_laboral' => ['nullable', 'string', 'max:100'],
            'salario_base' => ['required', 'numeric', 'min:0'],
            'moneda' => ['required', 'in:USD,BS'],
            'estatus' => ['nullable', 'in:Activo,Inactivo,Suspendido'],
            'capitaliza_intereses' => ['boolean'],
            'capitalizacion_autorizada_desde' => ['nullable', 'date', 'before_or_equal:today', 'required_if:capitaliza_intereses,1'],
            'capitalizacion_documento' => ['nullable', 'string', 'max:255', 'required_if:capitaliza_intereses,1'],
        ];
    }

    protected $messages = [
        'nombres.required' => 'Los nombres son obligatorios.',
        'apellidos.required' => 'Los apellidos son obligatorios.',
        'cedula.required' => 'La cédula es obligatoria.',
        'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',
    ];

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $patronoId = (int) session('patrono_id');
            $cedula = CedulaVenezolanaRule::formatear($this->cedula);

            if ($cedula && $patronoId) {
                $existe = Empleado::where('patrono_id', $patronoId)
                    ->where('cedula', $cedula)
                    ->exists();

                if ($existe) {
                    $validator->errors()->add('cedula', 'Ya existe un empleado con esta cédula en este patrono.');
                }
            }
        });
    }

    public function render(): View
    {
        return view('livewire.entidades.crear-empleado', [
            'patrono' => Patrono::find((int) session('patrono_id')),
        ]);
    }
}
