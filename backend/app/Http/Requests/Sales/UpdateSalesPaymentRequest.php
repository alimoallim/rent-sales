<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ProhibitsSalesCurrencyOverride;
use App\Http\Requests\Sales\Concerns\ValidatesSalesPayment;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSalesPaymentRequest extends FormRequest
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
        $payment = $this->route('payment');

        return array_merge($this->prohibitSalesCurrencyOverride(), [
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'discount' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'invoice_reference' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('sales_payments', 'invoice_reference')
                    ->where(fn ($query) => $query->where('sale_building_id', $payment->sale_building_id))
                    ->ignore($payment?->id),
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

            $payment = $this->route('payment');
            $client = Client::query()->find($payment?->client_id);
            if ($client === null || $payment === null) {
                return;
            }

            $this->assertSalesPaymentBusinessRules($validator, $client, $payment->id);
        });
    }
}
