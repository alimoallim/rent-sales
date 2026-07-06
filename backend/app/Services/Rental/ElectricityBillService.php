<?php

namespace App\Services\Rental;

use App\Enums\ElectricityBillStatus;
use App\Models\TenantElectricityBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectricityBillService
{
    public const CHARGE_PURPOSE = 'Electricity';

    public function __construct(
        private readonly ChargeBatchUtilitySyncService $utilitySync,
        private readonly MeterReadingResolver $meterReadingResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function calculateAmounts(array $data): array
    {
        $consumption = max(0, (int) $data['current_reading'] - (int) $data['previous_reading']);
        $rate = (string) ($data['rate'] ?? 0);
        $fixedFee = (string) ($data['fixed_fee'] ?? 0);
        $amount = bcadd(bcmul((string) $consumption, $rate, 2), $fixedFee, 2);

        return [
            'consumption' => $consumption,
            'amount' => $amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): TenantElectricityBill
    {
        $this->assertUniquePeriod($data['tenant_id'], $data['billing_month'], $data['billing_year']);

        $previousReading = $this->meterReadingResolver->resolvePreviousReading(
            TenantElectricityBill::class,
            (int) $data['tenant_id'],
            (int) $data['billing_month'],
            (int) $data['billing_year'],
            array_key_exists('previous_reading', $data) ? (int) $data['previous_reading'] : null,
            (int) $data['current_reading'],
        );

        $data['previous_reading'] = $previousReading;

        $calculated = $this->calculateAmounts($data);
        $amount = $data['amount'] ?? $calculated['amount'];

        return DB::transaction(function () use ($data, $userId, $calculated, $amount): TenantElectricityBill {
            $bill = TenantElectricityBill::query()->create([
                ...$data,
                'consumption' => $calculated['consumption'],
                'amount' => $amount,
                'status' => ElectricityBillStatus::Recorded,
                'created_by' => $userId,
            ])->fresh(['tenant', 'building']);

            $this->utilitySync->syncElectricityBill($bill);

            return $bill;
        });
    }

    public function markPaid(TenantElectricityBill $bill, ?string $amountPaid = null): TenantElectricityBill
    {
        $bill->update([
            'status' => ElectricityBillStatus::Paid,
            'amount_paid' => $amountPaid ?? $bill->amount,
        ]);

        return $bill->fresh(['tenant', 'building', 'rentCharge']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TenantElectricityBill $bill, array $data): TenantElectricityBill
    {
        $context = $this->meterReadingResolver->context(
            TenantElectricityBill::class,
            $bill->tenant_id,
            $bill->billing_month,
            $bill->billing_year,
        );

        if ($context['previous_reading_locked']) {
            unset($data['previous_reading']);
            $data['previous_reading'] = $context['previous_reading'];
        }

        $calculated = $this->calculateAmounts($data);
        $amount = $data['amount'] ?? $calculated['amount'];

        return DB::transaction(function () use ($bill, $data, $calculated, $amount): TenantElectricityBill {
            $bill->update([
                ...$data,
                'consumption' => $calculated['consumption'],
                'amount' => $amount,
            ]);

            $bill = $bill->fresh(['tenant', 'building']);

            $this->utilitySync->syncElectricityBill($bill);

            return $bill;
        });
    }

    private function assertUniquePeriod(int $tenantId, int $month, int $year, ?int $exceptId = null): void
    {
        $query = TenantElectricityBill::query()
            ->where('tenant_id', $tenantId)
            ->where('billing_month', $month)
            ->where('billing_year', $year);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'billing_month' => ['An electricity bill already exists for this tenant and period.'],
            ]);
        }
    }
}
