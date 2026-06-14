<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de una conversación DM (direct message) entre dos usuarios.
 */
class StoreDmConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_uuid' => [
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
            'recipient_uuid.not_in' => 'No puedes crear una conversación contigo mismo.',
        ];
    }
}
