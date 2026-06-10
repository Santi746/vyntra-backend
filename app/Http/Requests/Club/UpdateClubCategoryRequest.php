<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateClubCategoryRequest
 * 
 * Gestiona la validación para la actualización parcial de una categoría de canales existente en un club.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP PATCH
 */
class UpdateClubCategoryRequest extends FormRequest
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
            'category_uuid' => ['required', 'string', 'exists:club_categories,uuid'],
            'client_uuid' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:100'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
