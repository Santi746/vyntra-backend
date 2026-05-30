<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreChannelMessageRequest
 * 
 * Gestiona la validación para la creación de un nuevo mensaje dentro de un canal de club.
 * 
 * @package App\Http\Requests\Chat
 * @method string method() HTTP POST
 */
class StoreChannelMessageRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el futuro, aquí se validará si el miembro pertenece al club y al canal.
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
            'content' => ['required', 'string', 'max:4000'],
            'client_uuid' => ['required', 'uuid'], // Obligatorio en creación para deduplicar peticiones en tiempo real
            'parent_message_uuid' => ['nullable', 'uuid', 'exists:channel_messages,uuid'],
        ];
    }
}
