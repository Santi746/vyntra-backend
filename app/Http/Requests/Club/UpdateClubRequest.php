<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la actualización parcial de los datos de un club existente.
 *
 * @method string method() HTTP PATCH
 */
class UpdateClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // client_uuid removido: las peticiones PATCH son inherentemente idempotentes y modifican recursos existentes.
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string', 'max:500', 'nullable'],
            'category_tag' => ['sometimes', 'string', 'max:50'],
            'avatar_url' => ['sometimes', 'url', 'nullable'],
            'banner_url' => ['sometimes', 'url', 'nullable'],
        ];
    }
}
