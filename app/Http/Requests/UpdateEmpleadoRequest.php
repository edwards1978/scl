<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Empleado;
use App\Rules\CedulaVenezolanaRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'nombres'         => ['required', 'string', 'max:255'],
            'apellidos'       => ['required', 'string', 'max:255'],
            'cedula'          => ['required', 'string', 'max:15', new CedulaVenezolanaRule],
            'fecha_nacimiento'=> ['nullable', 'date', 'before:today'],
            'genero'          => ['nullable', Rule::in(['M', 'F'])],
            'telefono'        => ['nullable', 'string', 'max:50'],
            'correo'          => ['nullable', 'email', 'max:255'],
            'direccion'       => ['nullable', 'string'],
            'fecha_ingreso'   => ['required', 'date'],
            'cargo'           => ['nullable', 'string', 'max:255'],
            'departamento'    => ['nullable', 'string', 'max:255'],
            'tipo_contrato'   => ['required', Rule::in(['Determinado', 'Indeterminado', 'Obra'])],
            'tipo_salario'    => ['required', Rule::in(['Fijo', 'Variable', 'Mixto'])],
            'jornada_laboral' => ['nullable', 'string', 'max:100'],
            'salario_base'    => ['required', 'numeric', 'min:0'],
            'moneda'          => ['required', Rule::in(['USD', 'BS'])],
            'estatus'         => ['nullable', Rule::in(['Activo', 'Inactivo', 'Suspendido'])],
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
                }
            }
        });
    }
}
