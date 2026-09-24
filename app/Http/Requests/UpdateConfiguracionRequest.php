<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descuenta_ivss'      => $this->boolean('descuenta_ivss'),
            'descuenta_paro_fijo' => $this->boolean('descuenta_paro_fijo'),
            'descuenta_faov'      => $this->boolean('descuenta_faov'),
            'descuenta_inces'     => $this->boolean('descuenta_inces'),
            'mostrar_equivalencia_usd' => $this->boolean('mostrar_equivalencia_usd'),
        ]);
    }

    public function rules(): array
    {
        return [
            'dias_bonificacion'   => ['required', 'integer', 'min:1', 'max:120'],
            'descuenta_ivss'      => ['boolean'],
            'descuenta_paro_fijo' => ['boolean'],
            'descuenta_faov'      => ['boolean'],
            'descuenta_inces'     => ['boolean'],
            'mostrar_equivalencia_usd' => ['boolean'],
            'deposito_tipo'           => ['required', Rule::in(['contabilidad', 'fideicomiso', 'fondo'])],
            'anticipos_max_anio'      => ['required', 'integer', 'min:1', 'max:24'],
            'anticipos_pct_contabilidad' => ['required', 'integer', 'min:1', 'max:100'],
            'anticipos_pct_fideicomiso' => ['required', 'integer', 'min:1', 'max:100'],
            'anticipos_pct_fondo'     => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
