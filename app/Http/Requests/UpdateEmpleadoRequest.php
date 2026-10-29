<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Empleado;
use App\Rules\CedulaVenezolanaRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...StoreEmpleadoRequest::reglas(),
            'fecha_vigencia_salario' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'salario_base' => (string) parsear_numero((string) $this->input('salario_base')),
        ]);
    }

    public function withValidator($validator): void
    {
        $empleadoId = $this->route('empleado')->id;

        $validator->after(function ($validator) use ($empleadoId) {
            $patronoId = (int) session('patrono_id');
            $cedula = CedulaVenezolanaRule::formatear($this->input('cedula') ?? '');

            if ($cedula && $patronoId) {
                $existe = Empleado::where('patrono_id', $patronoId)
                    ->where('cedula', $cedula)
                    ->where('id', '!=', $empleadoId)
                    ->exists();

                if ($existe) {
                    $validator->errors()->add('cedula', 'Ya existe otro empleado con esta cédula en este patrono.');
                } elseif (Empleado::onlyTrashed()
                    ->where('patrono_id', $patronoId)
                    ->where('cedula', $cedula)
                    ->where('id', '!=', $empleadoId)
                    ->exists()) {
                    $validator->errors()->add('cedula', 'Existe un empleado eliminado con esta cédula. Restáurelo desde la papelera antes de usar esa cédula.');
                }
            }
        });
    }
}
