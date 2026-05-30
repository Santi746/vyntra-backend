<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreClubCategoryRequest
 * 
 * Gestiona la validación para la creación de una nueva categoría de canales dentro de un club.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP POST
 */
class StoreClubCategoryRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el controlador se verificará si el miembro tiene rol de administrador o permisos suficientes.
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
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
