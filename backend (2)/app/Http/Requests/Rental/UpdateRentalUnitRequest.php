<?php

namespace App\Http\Requests\Rental;

use App\Rules\UniqueUnitNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRentalUnitRequest extends FormRequest
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
        $unit = $this->route('unit');

        return [
            'house_number' => [
                'required',
                'string',
                'max:50',
                new UniqueUnitNumber('rental_units', 'rental_building_id', $unit?->rental_building_id, $unit?->id),
            ],
            'floor' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:2000'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }
}
