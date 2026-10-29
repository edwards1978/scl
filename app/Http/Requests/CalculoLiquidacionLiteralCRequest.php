<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CalculoLiquidacionLiteralC;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculoLiquidacionLiteralCRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['salario', 'anticipo', 'intereses_pagados', 'prestamo_saldo'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $this->merge([$campo => str_replace(',', '.', $valor)]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patrono_nombre' => ['required', 'string', 'max:255'],
            'patrono_rif' => ['nullable', 'string', 'max:20'],
            'trabajador_nombre' => ['required', 'string', 'max:255'],
            'trabajador_cedula' => ['nullable', 'string', 'max:20'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'fecha_ingreso' => ['required', 'date'],
            'fecha_egreso' => ['required', 'date', 'after_or_equal:fecha_ingreso'],
            'tipo_salario' => ['required', Rule::in(array_keys(CalculoLiquidacionLiteralC::tiposSalario()))],
            'salario' => ['required', 'numeric', 'gt:0'],
            'moneda' => ['required', Rule::in(array_keys(CalculoLiquidacionLiteralC::monedas()))],
            'dias_utilidades' => ['required', 'integer', 'min:30', 'max:120'],
            'anios_vacaciones_vencidas' => ['required', 'integer', 'min:0'],
            'anios_utilidades_vencidas' => ['required', 'integer', 'min:0'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'intereses_pagados' => ['nullable', 'numeric', 'min:0'],
            'prestamo_saldo' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('fecha_ingreso') || ! $this->filled('fecha_egreso')) {
                return;
            }

            try {
                $ingreso = Carbon::parse((string) $this->input('fecha_ingreso'));
                $egreso = Carbon::parse((string) $this->input('fecha_egreso'));
            } catch (\Throwable) {
                return;
            }

            if ($egreso->lt($ingreso)) {
                return;
            }

            $anios = (int) $ingreso->diffInYears($egreso);

            if ((int) $this->input('anios_vacaciones_vencidas', 0) > $anios) {
                $validator->errors()->add('anios_vacaciones_vencidas', 'Los años de vacaciones vencidas no pueden superar los años efectivos de servicio.');
            }

            if ((int) $this->input('anios_utilidades_vencidas', 0) > $anios) {
                $validator->errors()->add('anios_utilidades_vencidas', 'Los años de utilidades vencidas no pueden superar los años efectivos de servicio.');
            }
        });
    }
}
