<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class StoreShareholderBillRequest extends FormRequest
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
            'shareholder_id' => ['required', 'integer', 'exists:shareholders,id'],
            'rental_building_id' => ['required', 'integer', 'exists:rental_buildings,id'],
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'remark' => ['nullable', 'string', 'max:500'],
            'bill_date' => ['required', 'date'],
        ];
    }
}
