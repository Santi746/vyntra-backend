<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la creación de un nuevo mensaje dentro de un canal de club.
 *
 * @method string method() HTTP POST
 */
class StoreChannelMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
            'client_uuid' => ['required', 'uuid'], // Obligatorio en creación para deduplicar peticiones en tiempo real
            'parent_message_uuid' => ['nullable', 'uuid', 'exists:channel_messages,uuid'],
        ];
    }
}
