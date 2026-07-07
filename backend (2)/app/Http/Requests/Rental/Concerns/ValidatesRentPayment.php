<?php

namespace App\Http\Requests\Rental\Concerns;

use App\Models\Tenant;
use App\Services\Rental\TenantBalanceBreakdownService;
use App\Services\Rental\TenantMeterReadingReminderService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesRentPayment
{
    protected function assertRentPaymentBusinessRules(
        Validator $validator,
        Tenant $tenant,
        string $paidAt,
        ?int $excludePaymentId,
    ): void {
        $this->assertPositivePaymentTotal($validator);
        $this->assertRequiredMeterReadings($validator, $tenant, $paidAt);
        $this->assertOverpaymentAcknowledged($validator, $tenant, $excludePaymentId);
    }

    protected function assertPositivePaymentTotal(Validator $validator): void
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

    protected function assertRequiredMeterReadings(Validator $validator, Tenant $tenant, string $paidAt): void
    {
        $period = Carbon::parse($paidAt);
        $missing = app(TenantMeterReadingReminderService::class)->missingRequiredReadings(
            $tenant,
            (int) $period->month,
            (int) $period->year,
        );

        foreach ($missing as $reading) {
            $validator->errors()->add(
                'meter_reading.'.$reading['utility'],
                $reading['message'],
            );
        }
    }

    protected function assertOverpaymentAcknowledged(Validator $validator, Tenant $tenant, ?int $excludePaymentId): void
    {
        $amount = (string) $this->input('amount', '0');
        $discount = (string) $this->input('discount', '0');
        $paymentTotal = bcadd($amount, $discount, 2);

        if (bccomp($paymentTotal, '0', 2) <= 0) {
            return;
        }

        $totalDue = app(TenantBalanceBreakdownService::class)->totalDue($tenant, $excludePaymentId);

        if (bccomp($paymentTotal, $totalDue, 2) <= 0) {
            return;
        }

        if (! $this->boolean('overpayment_acknowledged')) {
            $validator->errors()->add(
                'overpayment_acknowledged',
                'This payment is more than the tenant owes. Confirm you are recording an overpayment on purpose.',
            );
        }
    }
}
