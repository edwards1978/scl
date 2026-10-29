<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('usuario'))->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'patronos' => [
                'array',
                Rule::requiredIf(fn (): bool => $this->input('rol') !== RolUsuario::Administrador->value),
            ],
            'patronos.*' => ['integer', 'exists:patronos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patronos.required' => 'Asigne al menos un patrono a los usuarios que no son administradores.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = strtolower((string) $this->input('email'));
            $usuarioId = $this->route('usuario')->id;

            if ($email && User::onlyTrashed()->where('email', $email)->where('id', '!=', $usuarioId)->exists()) {
                $validator->errors()->add('email', 'Existe un usuario eliminado con este correo. Restáurelo desde la papelera antes de usar ese correo.');
            }
        });
    }
}
