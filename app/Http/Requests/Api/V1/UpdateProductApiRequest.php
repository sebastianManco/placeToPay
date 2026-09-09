<?php

namespace App\Http\Requests\Api\V1;

class UpdateProductApiRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPatch = $this->isMethod('PATCH');

        return [
            'name' => [$isPatch ? 'sometimes' : 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => [$isPatch ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'stock' => [$isPatch ? 'sometimes' : 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'max:2048'],
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
            'name.required' => 'El nombre del producto es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 100 caracteres.',
            'price.required' => 'El precio del producto es obligatorio.',
            'price.numeric' => 'El precio debe ser un valor numérico.',
            'price.min' => 'El precio no puede ser un valor negativo.',
            'stock.required' => 'El stock del producto es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser un valor negativo.',
            'is_active.boolean' => 'El estado is_active debe ser verdadero o falso.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'image.image' => 'El archivo adjunto debe ser una imagen válida.',
            'image.max' => 'La imagen no puede exceder los 2 MB.',
        ];
    }
}
