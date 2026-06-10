<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreClubRoleRequest
 * 
 * Gestiona la validación para la creación de un nuevo rol personalizado dentro de un club.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP POST
 */
class StoreClubRoleRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el controlador se verificará si el miembro tiene permisos de 'manage_roles'.
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'], // Requerido para deduplicar la creación en tiempo real
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/i'],
            'permissions' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Obtiene los mensajes personalizados para los errores de validación.
     * 
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'El color debe ser un valor hexadecimal válido de 6 caracteres (ej. #FFFFFF).',
        ];
    }
}
