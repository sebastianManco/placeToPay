<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('in_stock')) {
            $this->merge([
                'in_stock' => filter_var($this->input('in_stock'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:name_asc,name_desc,price_asc,price_desc,newest'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'El término de búsqueda no puede superar los 100 caracteres.',
            'category.exists' => 'La categoría seleccionada no es válida.',
            'min_price.numeric' => 'El precio mínimo debe ser un número válido.',
            'min_price.min' => 'El precio mínimo no puede ser negativo.',
            'max_price.numeric' => 'El precio máximo debe ser un número válido.',
            'max_price.min' => 'El precio máximo no puede ser negativo.',
            'sort_by.in' => 'El criterio de ordenamiento seleccionado no es válido.',
        ];
    }
}
