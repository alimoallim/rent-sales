<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalUnitRequest extends FormRequest
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
            'rental_building_id' => ['required', 'integer', 'exists:rental_buildings,id'],
            'house_number' => ['required', 'string', 'max:50'],
            'floor' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:2000'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
