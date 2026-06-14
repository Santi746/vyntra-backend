<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la actualización parcial de un rol de club existente.
 *
 * @method string method() HTTP PATCH
 */
class UpdateClubRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'exists:club_roles,uuid'],
            'client_uuid' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:50'],
            'color' => ['sometimes', 'string', 'regex:/^#[a-fA-F0-9]{6}$/i'],
            'permissions' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'El color debe ser un valor hexadecimal válido de 6 caracteres (ej. #FFFFFF).',
        ];
    }
}
