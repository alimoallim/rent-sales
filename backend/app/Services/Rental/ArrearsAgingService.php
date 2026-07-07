<?php

namespace App\Services\Rental;

use App\Enums\RentPaymentStatus;
use App\Enums\TenantStatus;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Support\MoneyConfig;
use Illuminate\Support\Carbon;

class ArrearsAgingService
{
    public function __construct(
        private readonly TenantBalanceBreakdownService $balanceBreakdown,
    ) {}

    /**
     * Age outstanding balances by billing period using FIFO payment allocation.
     *
     * @return array{
     *     generated_at: string,
     *     currency_code: string,
     *     as_of: string,
     *     filters: array<string, mixed>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, string|int>
     * }
     */
    public function report(?int $buildingId = null, bool $outstandingOnly = true, ?Carbon $asOf = null): array
    {
        $asOf ??= now()->startOfDay();

        $tenants = Tenant::query()
            ->with(['building', 'unit'])
            ->where('status', TenantStatus::Active)
            ->when($buildingId, fn ($q) => $q->where('rental_building_id', $buildingId))
            ->orderBy('name')
            ->get();

        $rows = [];
        $totals = [
            'tenants' => 0,
            'total_balance' => '0.00',
            'current' => '0.00',
            'days_31_60' => '0.00',
            'days_61_90' => '0.00',
            'days_90_plus' => '0.00',
        ];

        foreach ($tenants as $tenant) {
            $aging = $this->tenantAging($tenant, $asOf);

            if ($outstandingOnly && bccomp($aging['total_balance'], '0', 2) <= 0) {
                continue;
            }

            $rows[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'building_id' => $tenant->rental_building_id,
                'building_name' => $tenant->building?->name,
                'unit_label' => $tenant->unit?->house_number,
                'total_balance' => $aging['total_balance'],
                'current' => $aging['current'],
                'days_31_60' => $aging['days_31_60'],
                'days_61_90' => $aging['days_61_90'],
                'days_90_plus' => $aging['days_90_plus'],
                'oldest_overdue_period' => $aging['oldest_overdue_period'],
                'max_days_overdue' => $aging['max_days_overdue'],
            ];

            $totals['tenants']++;
            foreach (['total_balance', 'current', 'days_31_60', 'days_61_90', 'days_90_plus'] as $key) {
                $totals[$key] = bcadd($totals[$key], $aging[$key], 2);
            }
        }

        return [
            'generated_at' => now()->toISOString(),
            'currency_code' => MoneyConfig::rentalCurrency(),
            'as_of' => $asOf->toDateString(),
            'filters' => [
                'building_id' => $buildingId,
                'outstanding_only' => $outstandingOnly,
            ],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @return array{
     *     total_balance: string,
     *     current: string,
     *     days_31_60: string,
     *     days_61_90: string,
     *     days_90_plus: string,
     *     oldest_overdue_period: string|null,
     *     max_days_overdue: int
     * }
     */
    public function tenantAging(Tenant $tenant, Carbon $asOf): array
    {
        $buckets = [
            'current' => '0.00',
            'days_31_60' => '0.00',
            'days_61_90' => '0.00',
            'days_90_plus' => '0.00',
        ];

        $charges = RentCharge::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('billing_year')
            ->orderBy('billing_month')
            ->orderBy('id')
            ->get();

        $paymentPool = $this->paymentPool($tenant->id);
        $oldestPeriod = null;
        $maxDaysOverdue = 0;

        foreach ($charges as $charge) {
            $chargeAmount = (string) $charge->total_amount;
            $applied = bccomp($paymentPool, $chargeAmount, 2) >= 0 ? $chargeAmount : $paymentPool;
            $unpaid = bcsub($chargeAmount, $applied, 2);
            $paymentPool = bcsub($paymentPool, $applied, 2);

            if (bccomp($unpaid, '0', 2) <= 0) {
                continue;
            }

            $daysPastDue = $this->daysPastDue($charge->billing_year, $charge->billing_month, $asOf);
            $bucket = $this->bucketKey($daysPastDue);
            $buckets[$bucket] = bcadd($buckets[$bucket], $unpaid, 2);

            if ($oldestPeriod === null) {
                $oldestPeriod = sprintf('%04d-%02d', $charge->billing_year, $charge->billing_month);
            }

            $maxDaysOverdue = max($maxDaysOverdue, $daysPastDue);
        }

        $totalBalance = $this->balanceBreakdown->totalDue($tenant);

        return [
            'total_balance' => $totalBalance,
            'current' => $buckets['current'],
            'days_31_60' => $buckets['days_31_60'],
            'days_61_90' => $buckets['days_61_90'],
            'days_90_plus' => $buckets['days_90_plus'],
            'oldest_overdue_period' => bccomp($totalBalance, '0', 2) > 0 ? $oldestPeriod : null,
            'max_days_overdue' => bccomp($totalBalance, '0', 2) > 0 ? $maxDaysOverdue : 0,
        ];
    }

    private function paymentPool(int $tenantId): string
    {
        $amount = (string) RentPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', RentPaymentStatus::Active)
            ->sum('amount');

        $discount = (string) RentPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', RentPaymentStatus::Active)
            ->sum('discount');

        return bcadd($amount, $discount, 2);
    }

    private function daysPastDue(int $year, int $month, Carbon $asOf): int
    {
        $dueDate = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

        if ($dueDate->greaterThan($asOf)) {
            return 0;
        }

        return (int) $dueDate->diffInDays($asOf);
    }

    private function bucketKey(int $daysPastDue): string
    {
        if ($daysPastDue <= 30) {
            return 'current';
        }

        if ($daysPastDue <= 60) {
            return 'days_31_60';
        }

        if ($daysPastDue <= 90) {
            return 'days_61_90';
        }

        return 'days_90_plus';
    }
}
