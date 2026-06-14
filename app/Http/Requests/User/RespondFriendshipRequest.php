<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para responder (aceptar o rechazar) una solicitud de amistad existente.
 *
 * @method string method() HTTP PATCH
 */
class RespondFriendshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'uuid'], // Opcional porque la actualización de estado (PATCH) sobre un UUID de solicitud es intrínsecamente idempotente.
            'action' => ['required', 'string', 'in:accept,decline'], // Sincronizado con el Frontend (Next.js) que envía "decline" para rechazar.
        ];
    }

    /**
     * Normaliza los valores del campo `action` antes de validar.
     *
     * Acepta tanto 'reject' (sinónimo legacy del frontend mock) como 'decline' (canónico de la API)
     * y los mapea a 'decline' para mantener una única representación interna del estado 'declined'.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('action') && $this->input('action') === 'reject') {
            $this->merge(['action' => 'decline']);
        }
    }
}
