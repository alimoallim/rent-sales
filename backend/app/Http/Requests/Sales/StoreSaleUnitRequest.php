<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ProhibitsSalesCurrencyOverride;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleUnitRequest extends FormRequest
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
            'sale_building_id' => ['required', 'integer', 'exists:sale_buildings,id'],
            'house_number' => ['required', 'string', 'max:50'],
            'floor' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'list_price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);
    }
}
