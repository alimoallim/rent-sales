<?php

namespace App\Http\Requests\Rental;

use App\Enums\EmployeeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
            'rental_building_id' => ['nullable', 'integer', 'exists:rental_buildings,id'],
            'name' => ['sometimes', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'salary' => ['sometimes', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
        ];
    }
}
