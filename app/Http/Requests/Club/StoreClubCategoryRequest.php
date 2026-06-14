<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la creación de una nueva categoría de canales dentro de un club.
 *
 * @method string method() HTTP POST
 */
class StoreClubCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'], // Requerido para deduplicar la creación en tiempo real
            'name' => ['required', 'string', 'max:100'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
