<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RifVenezolanoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatronoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        //
    }

    public function rules(): array
    {
        $patronoId = $this->route('patrono')->id;

        return [
            'rif' => ['required', 'string', 'max:20', new RifVenezolanoRule, Rule::unique('patronos')->ignore($patronoId)],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'registro_mercantil' => ['nullable', 'string', 'max:255'],
            'direccion_fiscal' => ['nullable', 'string'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:255'],
            'estado' => ['nullable', Rule::in(['Activo', 'Inactivo'])],
            'deposito_tipo' => ['required', Rule::in(['contabilidad', 'fideicomiso', 'fondo'])],
        ];
    }
}
