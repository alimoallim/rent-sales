<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollEntryRequest extends FormRequest
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
            'employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'rental_building_id' => ['sometimes', 'integer', 'exists:rental_buildings,id'],
            'billing_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'salary_amount' => ['sometimes', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'paid_at' => ['sometimes', 'date'],
        ];
    }
}
