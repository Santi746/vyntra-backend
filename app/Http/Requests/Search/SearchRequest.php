<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la búsqueda global de clubes y usuarios.
 *
 * @package App\Http\Requests\Search
 *
 * @return array<string, mixed>
 */
class SearchRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de la búsqueda.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:100'],
            'filter' => ['sometimes', 'string', 'in:all,clubs,users'],
        ];
    }
}
