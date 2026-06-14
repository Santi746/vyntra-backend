<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la creación de un nuevo rol personalizado dentro de un club.
 *
 * @method string method() HTTP POST
 */
class StoreClubRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'], // Requerido para deduplicar la creación en tiempo real
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/i'],
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
