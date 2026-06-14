<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el envío de una nueva solicitud de amistad.
 *
 * Implementa el patrón client_uuid (UUID v4) generado en el frontend para
 * deduplicación en tiempo real. La columna client_uuid en la tabla friendships
 * tiene restricción UNIQUE compuesta (sender_uuid, client_uuid).
 *
 *
 * @return array<string, mixed>
 */
class StoreFriendshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'receiver_uuid' => [
                'required',
                'uuid',
                'exists:users,uuid',
                Rule::notIn([$this->user()->uuid]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_uuid.not_in' => 'No puedes enviarte una solicitud a ti mismo.',
        ];
    }
}
