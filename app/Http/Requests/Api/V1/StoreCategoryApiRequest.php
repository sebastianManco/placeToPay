<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class StoreCategoryApiRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 100 caracteres.',
            'name.unique' => 'Ya existe una categoría registrada con este nombre.',
            'description.max' => 'La descripción no puede exceder los 1000 caracteres.',
            'is_active.boolean' => 'El campo is_active debe ser verdadero o falso.',
        ];
    }
}
