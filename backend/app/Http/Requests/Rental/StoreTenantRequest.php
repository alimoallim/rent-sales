<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
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
            'rental_building_id' => ['required', 'integer', 'exists:rental_buildings,id,deleted_at,NULL'],
            'rental_unit_id' => ['required', 'integer', 'exists:rental_units,id,deleted_at,NULL'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:100'],
            'passport_or_id' => ['nullable', 'string', 'max:50'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'service_amount' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'requires_water_metering' => ['nullable', 'boolean'],
            'requires_electricity_metering' => ['nullable', 'boolean'],
            'next_of_kin_name' => ['nullable', 'string', 'max:100'],
            'next_of_kin_address' => ['nullable', 'string', 'max:200'],
            'next_of_kin_id' => ['nullable', 'string', 'max:50'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30'],
            'start_date' => ['nullable', 'date'],
        ];
    }
}
