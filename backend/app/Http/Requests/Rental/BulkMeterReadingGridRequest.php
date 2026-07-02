<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMeterReadingGridRequest extends FormRequest
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
            'utility' => ['required', 'string', Rule::in(['water', 'electricity'])],
            'building_id' => ['required', 'integer', 'exists:rental_buildings,id'],
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
