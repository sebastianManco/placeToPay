<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', [
                Report::TYPE_COMPLETE,
                Report::TYPE_SALES,
                Report::TYPE_ORDERS,
                Report::TYPE_TOP_PRODUCTS,
                Report::TYPE_PAYMENTS,
                Report::TYPE_INVENTORY_ALERTS,
            ]),
            'format' => 'required|string|in:' . implode(',', [
                Report::FORMAT_PDF,
                Report::FORMAT_XLSX,
                Report::FORMAT_BOTH,
            ]),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'low_stock_threshold' => 'nullable|integer|min:1|max:1000',
            'days_inactive' => 'nullable|integer|min:1|max:365',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título del reporte',
            'type' => 'tipo de reporte',
            'format' => 'formato de exportación',
            'date_from' => 'fecha inicial',
            'date_to' => 'fecha final',
            'low_stock_threshold' => 'umbral de stock bajo',
            'days_inactive' => 'días sin rotación',
        ];
    }
}