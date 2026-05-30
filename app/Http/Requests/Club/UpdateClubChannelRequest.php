<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateClubChannelRequest
 * 
 * Gestiona la validación para la actualización parcial de un canal de club existente.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP PATCH
 */
class UpdateClubChannelRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el controlador se verificará si el miembro tiene permisos de 'manage_channels'.
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
            // client_uuid removido: las peticiones PATCH son inherentemente idempotentes y modifican recursos existentes.
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string', 'max:500', 'nullable'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
