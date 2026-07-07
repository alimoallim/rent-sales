<?php

namespace App\Http\Requests\Sales\Concerns;

use App\Models\Client;
use App\Services\Sales\ClientBalanceCalculator;
use Illuminate\Validation\Validator;

trait ValidatesSalesPayment
{
    protected function assertSalesPaymentBusinessRules(
        Validator $validator,
        Client $client,
        ?int $excludePaymentId,
    ): void {
        $this->assertPositiveSalesPaymentTotal($validator);
        $this->assertSalesPaymentWithinBalance($validator, $client, $excludePaymentId);
    }

    protected function assertPositiveSalesPaymentTotal(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $amount = (string) $this->input('amount', '0');
        $discount = (string) $this->input('discount', '0');
        $paymentTotal = bcadd($amount, $discount, 2);

        if (bccomp($paymentTotal, '0', 2) <= 0) {
            $validator->errors()->add(
                'amount',
                'Enter a payment amount greater than zero.',
            );
        }
    }

    protected function assertSalesPaymentWithinBalance(
        Validator $validator,
        Client $client,
        ?int $excludePaymentId,
    ): void {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $amount = (string) $this->input('amount', '0');
        $discount = (string) $this->input('discount', '0');
        $paymentTotal = bcadd($amount, $discount, 2);

        if (bccomp($paymentTotal, '0', 2) <= 0) {
            return;
        }

        $totalDue = app(ClientBalanceCalculator::class)->amountOwed($client, $excludePaymentId);

        if (bccomp($paymentTotal, $totalDue, 2) > 0) {
            $validator->errors()->add(
                'amount',
                'Payment cannot exceed the client outstanding balance.',
            );
        }
    }
}
