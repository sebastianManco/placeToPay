<?php

namespace App\Http\Requests\Api\V1;

class UpdateProductStockApiRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stock' => ['required_without:adjustment', 'nullable', 'integer', 'min:0'],
            'adjustment' => ['required_without:stock', 'nullable', 'integer'],
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
            'stock.required_without' => 'Debe indicar el nuevo valor de stock o un valor de ajuste (adjustment).',
            'stock.integer' => 'El valor de stock debe ser un número entero.',
            'stock.min' => 'El valor de stock no puede ser negativo.',
            'adjustment.required_without' => 'Debe indicar un ajuste de inventario o un valor absoluto de stock.',
            'adjustment.integer' => 'El valor de ajuste debe ser un número entero (positivo o negativo).',
        ];
    }
}
