<?php

namespace App\Services\Rental;

use App\Enums\WaterBillStatus;
use App\Models\TenantWaterBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaterBillService
{
    public const CHARGE_PURPOSE = 'Water';

    public function __construct(
        private readonly ChargeBatchUtilitySyncService $utilitySync,
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
    public function create(array $data, int $userId): TenantWaterBill
    {
        $this->assertUniquePeriod($data['tenant_id'], $data['billing_month'], $data['billing_year']);

        $calculated = $this->calculateAmounts($data);
        $amount = $data['amount'] ?? $calculated['amount'];

        return DB::transaction(function () use ($data, $userId, $calculated, $amount): TenantWaterBill {
            $bill = TenantWaterBill::query()->create([
                ...$data,
                'consumption' => $calculated['consumption'],
                'amount' => $amount,
                'status' => WaterBillStatus::Recorded,
                'created_by' => $userId,
            ])->fresh(['tenant', 'building']);

            $this->utilitySync->syncWaterBill($bill);

            return $bill;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TenantWaterBill $bill, array $data): TenantWaterBill
    {
        $calculated = $this->calculateAmounts($data);
        $amount = $data['amount'] ?? $calculated['amount'];

        return DB::transaction(function () use ($bill, $data, $calculated, $amount): TenantWaterBill {
            $bill->update([
                ...$data,
                'consumption' => $calculated['consumption'],
                'amount' => $amount,
            ]);

            $bill = $bill->fresh(['tenant', 'building']);

            $this->utilitySync->syncWaterBill($bill);

            return $bill;
        });
    }

    public function markPaid(TenantWaterBill $bill, ?string $amountPaid = null): TenantWaterBill
    {
        $bill->update([
            'status' => WaterBillStatus::Paid,
            'amount_paid' => $amountPaid ?? $bill->amount,
        ]);

        return $bill->fresh(['tenant', 'building', 'rentCharge']);
    }

    private function assertUniquePeriod(int $tenantId, int $month, int $year, ?int $exceptId = null): void
    {
        $query = TenantWaterBill::query()
            ->where('tenant_id', $tenantId)
            ->where('billing_month', $month)
            ->where('billing_year', $year);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'billing_month' => ['A water bill already exists for this tenant and period.'],
            ]);
        }
    }
}
