<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreClubRequest
 * 
 * Gestiona la validación para la creación de un nuevo club o servidor de comunidad.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP POST
 */
class StoreClubRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En este caso, cualquier usuario autenticado está autorizado a crear un club propio.
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
            'name' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'string', 'max:500', 'nullable'],
            'category_tag' => ['required', 'string', 'max:50'],
            'logo_url' => ['sometimes', 'url', 'nullable'],
            'banner_url' => ['sometimes', 'url', 'nullable'],
        ];
    }
}
