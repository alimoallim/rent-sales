<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'building_id' => ['nullable', 'integer', 'exists:sale_buildings,id,deleted_at,NULL'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'outstanding_only' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'include_cancelled' => ['nullable', 'boolean'],
            'format' => ['nullable', 'in:json,csv'],
        ];
    }
}
