<?php

namespace App\Http\Requests\Rental;

use App\Http\Requests\Rental\Concerns\ValidatesRentPayment;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRentPaymentRequest extends FormRequest
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
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'rental_building_id' => ['required', 'integer', 'exists:rental_buildings,id'],
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

            $tenant = Tenant::query()->find($this->integer('tenant_id'));
            if ($tenant === null) {
                return;
            }

            $this->assertRentPaymentBusinessRules(
                $validator,
                $tenant,
                $this->string('paid_at')->toString(),
                null,
            );
        });
    }
}
