<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Report;

class StoreReportApiRequest extends BaseApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', [
                Report::TYPE_COMPLETE,
                Report::TYPE_SALES,
                Report::TYPE_ORDERS,
                Report::TYPE_TOP_PRODUCTS,
                Report::TYPE_PAYMENTS,
                Report::TYPE_INVENTORY_ALERTS,
            ])],
            'format' => ['required', 'string', 'in:' . implode(',', [
                Report::FORMAT_PDF,
                Report::FORMAT_XLSX,
                Report::FORMAT_BOTH,
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'days_inactive' => ['nullable', 'integer', 'min:1', 'max:365'],
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
            'title.required' => 'El título del reporte es obligatorio.',
            'type.required' => 'El tipo de reporte es obligatorio.',
            'type.in' => 'El tipo de reporte seleccionado no es válido. Opciones: complete, sales, orders, top_products, payments, inventory_alerts.',
            'format.required' => 'El formato del reporte es obligatorio.',
            'format.in' => 'El formato seleccionado no es válido. Opciones: pdf, xlsx, both.',
            'date_from.date' => 'La fecha inicial debe ser una fecha válida.',
            'date_to.date' => 'La fecha final debe ser una fecha válida.',
            'date_to.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            'low_stock_threshold.integer' => 'El umbral de stock bajo debe ser un número entero.',
            'days_inactive.integer' => 'Los días de inactividad deben ser un número entero.',
        ];
    }
}
