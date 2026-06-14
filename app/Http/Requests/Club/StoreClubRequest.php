<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la creación de un nuevo club o servidor de comunidad.
 *
 * @method string method() HTTP POST
 */
class StoreClubRequest extends FormRequest
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
            'description' => ['sometimes', 'string', 'max:500', 'nullable'],
            'category_tag' => ['required', 'string', 'max:50'],
            'avatar_url' => ['sometimes', 'url', 'nullable'],
            'banner_url' => ['sometimes', 'url', 'nullable'],
        ];
    }
}
