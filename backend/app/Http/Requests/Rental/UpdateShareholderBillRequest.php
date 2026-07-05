<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShareholderBillRequest extends FormRequest
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
            'shareholder_id' => ['sometimes', 'integer', 'exists:shareholders,id,deleted_at,NULL'],
            'rental_building_id' => ['sometimes', 'integer', 'exists:rental_buildings,id,deleted_at,NULL'],
            'amount' => ['sometimes', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'remark' => ['nullable', 'string', 'max:500'],
            'bill_date' => ['sometimes', 'date'],
        ];
    }
}
