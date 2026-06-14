<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la actualización parcial de un canal de club existente.
 *
 * @method string method() HTTP PATCH
 */
class UpdateClubChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string', 'max:500', 'nullable'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }
}
