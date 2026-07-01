<?php

namespace App\Http\Requests\Rental;

use App\Http\Requests\Rental\Concerns\ValidatesRentPayment;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRentPaymentRequest extends FormRequest
{
    use ValidatesRentPayment;

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
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'discount' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'invoice_reference' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['required', 'date'],
            'overpayment_acknowledged' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $payment = $this->route('rentPayment');
            $tenant = Tenant::query()->find($payment?->tenant_id);
            if ($tenant === null || $payment === null) {
                return;
            }

            $this->assertRentPaymentBusinessRules(
                $validator,
                $tenant,
                $this->string('paid_at')->toString(),
                $payment->id,
            );
        });
    }
}
