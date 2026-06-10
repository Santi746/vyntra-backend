<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreClubChannelRequest
 * 
 * Gestiona la validación para la creación de un nuevo canal (texto o voz) dentro de un club.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP POST
 */
class StoreClubChannelRequest extends FormRequest
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
            'client_uuid' => ['required', 'uuid'], // Requerido para deduplicar la creación en tiempo real
            'category_uuid' => ['required', 'uuid', 'exists:club_categories,uuid'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:text,voice'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
