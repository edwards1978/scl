<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUsuarioRequest extends FormRequest
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
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
}
