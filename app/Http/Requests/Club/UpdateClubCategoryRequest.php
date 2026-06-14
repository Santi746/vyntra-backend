<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la actualización parcial de una categoría de canales existente en un club.
 *
 * @method string method() HTTP PATCH
 */
class UpdateClubCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
