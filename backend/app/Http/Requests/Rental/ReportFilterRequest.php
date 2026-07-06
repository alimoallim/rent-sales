<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
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
            'building_id' => ['nullable', 'integer', 'exists:rental_buildings,id,deleted_at,NULL'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'billing_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'outstanding_only' => ['nullable', 'boolean'],
            'include_voided' => ['nullable', 'boolean'],
            'as_of' => ['nullable', 'date'],
            'format' => ['nullable', 'in:json,csv'],
        ];
    }
}
