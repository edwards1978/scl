<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Patrono;
use App\Rules\RifVenezolanoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatronoRequest extends FormRequest
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
        return [
            'rif' => ['required', 'string', 'max:20', new RifVenezolanoRule, Rule::unique('patronos')->whereNull('deleted_at')],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rif = RifVenezolanoRule::formatear($this->input('rif') ?? '');

            if ($rif) {
                $eliminado = Patrono::onlyTrashed()->where('rif', $rif)->exists();

                if ($eliminado) {
                    $validator->errors()->add('rif', 'Existe un patrono eliminado con este RIF. Restáurelo desde la papelera antes de crear uno nuevo.');
                }
            }
        });
    }
}
