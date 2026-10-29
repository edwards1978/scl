<?php

declare(strict_types=1);

namespace App\Livewire\Entidades;

use App\Models\Patrono;
use App\Rules\RifVenezolanoRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class CrearPatrono extends Component
{
    public string $rif = '';

    public string $razon_social = '';

    public string $nombre_comercial = '';

    public string $registro_mercantil = '';

    public string $direccion_fiscal = '';

    public string $representante_legal = '';

    public string $telefono = '';

    public string $correo = '';

    public bool $descuenta_ivss = false;

    public bool $descuenta_paro_fijo = false;

    public bool $descuenta_faov = false;

    public bool $descuenta_inces = false;

    public bool $mostrar_equivalencia_usd = false;

    public string $deposito_tipo = 'contabilidad';

    public int $anticipos_max_anio = 2;

    public int $anticipos_pct_contabilidad = 100;

    public int $anticipos_pct_fideicomiso = 75;

    public int $anticipos_pct_fondo = 75;

    public bool $guardando = false;

    public ?string $exito = null;

    public function mount(): void
    {
        Gate::authorize('administrar');
    }

    public function guardar(): void
    {
        Gate::authorize('administrar');

        $this->guardando = true;
        $this->exito = null;

        $this->validate();

        DB::transaction(function () {
            Patrono::create([
                'rif' => $this->rif,
                'razon_social' => $this->razon_social,
                'nombre_comercial' => $this->nombre_comercial ?: null,
                'registro_mercantil' => $this->registro_mercantil ?: null,
                'direccion_fiscal' => $this->direccion_fiscal ?: null,
                'representante_legal' => $this->representante_legal ?: null,
                'telefono' => $this->telefono ?: null,
                'correo' => $this->correo ?: null,
                'estado' => 'Activo',
                'descuenta_ivss' => $this->descuenta_ivss,
                'descuenta_paro_fijo' => $this->descuenta_paro_fijo,
                'descuenta_faov' => $this->descuenta_faov,
                'descuenta_inces' => $this->descuenta_inces,
                'mostrar_equivalencia_usd' => $this->mostrar_equivalencia_usd,
                'deposito_tipo' => $this->deposito_tipo,
                'anticipos_max_anio' => $this->anticipos_max_anio,
                'anticipos_pct_contabilidad' => $this->anticipos_pct_contabilidad,
                'anticipos_pct_fideicomiso' => $this->anticipos_pct_fideicomiso,
                'anticipos_pct_fondo' => $this->anticipos_pct_fondo,
            ]);
        });

        $this->dispatch('patrono-creado');
        $this->exito = 'Patrono creado correctamente.';
        $this->reset(['rif', 'razon_social', 'nombre_comercial', 'registro_mercantil',
            'direccion_fiscal', 'representante_legal', 'telefono', 'correo',
            'descuenta_ivss', 'descuenta_paro_fijo', 'descuenta_faov', 'descuenta_inces',
            'mostrar_equivalencia_usd']);
        $this->guardando = false;
    }

    protected function rules(): array
    {
        return [
            'rif' => ['required', 'string', 'max:20', new RifVenezolanoRule],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'registro_mercantil' => ['nullable', 'string', 'max:255'],
            'direccion_fiscal' => ['nullable', 'string'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'descuenta_ivss' => ['boolean'],
            'descuenta_paro_fijo' => ['boolean'],
            'descuenta_faov' => ['boolean'],
            'descuenta_inces' => ['boolean'],
            'mostrar_equivalencia_usd' => ['boolean'],
            'deposito_tipo' => ['nullable', Rule::in(['contabilidad', 'fideicomiso', 'fondo'])],
            'anticipos_max_anio' => ['nullable', 'integer', 'min:1', 'max:24'],
            'anticipos_pct_contabilidad' => ['nullable', 'integer', 'min:1', 'max:100'],
            'anticipos_pct_fideicomiso' => ['nullable', 'integer', 'min:1', 'max:100'],
            'anticipos_pct_fondo' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected $messages = [
        'rif.required' => 'El RIF es obligatorio.',
        'rif.unique' => 'Ya existe un patrono con este RIF.',
        'razon_social.required' => 'La Razón Social es obligatoria.',
    ];

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rif = RifVenezolanoRule::formatear($this->rif);

            if ($rif) {
                $existe = Patrono::where('rif', $rif)->exists();
                if ($existe) {
                    $validator->errors()->add('rif', 'Ya existe un patrono con este RIF.');
                }
            }
        });
    }

    public function render(): View
    {
        return view('livewire.entidades.crear-patrono');
    }
}
