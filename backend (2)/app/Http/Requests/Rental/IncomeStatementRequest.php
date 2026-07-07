<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class IncomeStatementRequest extends FormRequest
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
            'building_id' => ['required', 'integer', 'exists:rental_buildings,id,deleted_at,NULL'],
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mode' => ['nullable', 'in:unified,legacy'],
            'format' => ['nullable', 'in:json,csv'],
        ];
    }
}
