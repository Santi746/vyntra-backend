<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la creación de un nuevo canal (texto o voz) dentro de un club.
 *
 * @method string method() HTTP POST
 */
class StoreClubChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
