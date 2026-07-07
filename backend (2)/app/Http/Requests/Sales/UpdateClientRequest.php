<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ProhibitsSalesCurrencyOverride;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    use ProhibitsSalesCurrencyOverride;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->prohibitSalesCurrencyOverride(), [
            'sale_building_id' => ['required', 'integer', 'exists:sale_buildings,id,deleted_at,NULL'],
            'sale_unit_id' => ['required', 'integer', 'exists:sale_units,id,deleted_at,NULL'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:100'],
            'passport_or_id' => ['nullable', 'string', 'max:50'],
            'agreed_sale_price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'voucher_number' => ['nullable', 'string', 'max:50'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'next_of_kin_name' => ['nullable', 'string', 'max:100'],
            'next_of_kin_address' => ['nullable', 'string', 'max:200'],
            'next_of_kin_id' => ['nullable', 'string', 'max:50'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30'],
            'registration_date' => ['nullable', 'date'],
        ]);
    }
}
