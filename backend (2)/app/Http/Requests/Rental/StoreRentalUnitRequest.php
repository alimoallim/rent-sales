<?php

namespace App\Http\Requests\Rental;

use App\Rules\UniqueUnitNumber;
use Illuminate\Foundation\Http\FormRequest;

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
            'rental_building_id' => ['required', 'integer', 'exists:rental_buildings,id,deleted_at,NULL'],
            'house_number' => [
                'required',
                'string',
                'max:50',
                new UniqueUnitNumber('rental_units', 'rental_building_id', $this->input('rental_building_id')),
            ],
            'floor' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:2000'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
