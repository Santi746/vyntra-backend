<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la búsqueda global de clubes y usuarios.
 */
class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:100'],
            'filter' => ['sometimes', 'string', 'in:all,clubs,users'],
        ];
    }
}
