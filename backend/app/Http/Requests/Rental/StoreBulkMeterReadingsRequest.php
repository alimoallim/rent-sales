<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkMeterReadingsRequest extends FormRequest
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
            'rental_building_id' => ['required', 'integer', 'exists:rental_buildings,id,deleted_at,NULL'],
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'readings.*.current_reading' => ['nullable', 'integer', 'min:0'],
            'readings.*.previous_reading' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
