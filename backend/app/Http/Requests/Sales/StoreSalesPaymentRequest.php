<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ProhibitsSalesCurrencyOverride;
use App\Http\Requests\Sales\Concerns\ValidatesSalesPayment;
use App\Models\Client;
use App\Rules\BelongsToParentBuilding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesPaymentRequest extends FormRequest
{
    use ProhibitsSalesCurrencyOverride;
    use ValidatesSalesPayment;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $buildingId = $this->integer('sale_building_id');

        return array_merge($this->prohibitSalesCurrencyOverride(), [
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
                new BelongsToParentBuilding('clients', 'id', $buildingId, 'sale_building_id'),
            ],
            'sale_building_id' => ['required', 'integer', 'exists:sale_buildings,id,deleted_at,NULL'],
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'discount' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'invoice_reference' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('sales_payments', 'invoice_reference')
                    ->where(fn ($query) => $query->where('sale_building_id', $buildingId)),
            ],
            'bank' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:200'],
            'paid_at' => ['required', 'date'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $client = Client::query()->find($this->integer('client_id'));
            if ($client === null) {
                return;
            }

            $this->assertSalesPaymentBusinessRules($validator, $client, null);
        });
    }
}
