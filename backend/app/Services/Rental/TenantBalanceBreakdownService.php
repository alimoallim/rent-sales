<?php

namespace App\Services\Rental;

use App\Enums\RentPaymentStatus;
use App\Support\MoneyConfig;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;

class TenantBalanceBreakdownService
{
    public const PURPOSE_RENT_SERVICE = 'Rent + service';

    public const PURPOSE_RENT_SERVICE_GENERATOR = 'Rent + service + generator';

    public const PURPOSE_WATER = 'Water';

    public const PURPOSE_ELECTRICITY = 'Electricity';

    public const PURPOSE_ADJUSTMENT = 'Adjustment';

    /** @return list<string> */
    public static function rentServicePurposes(): array
    {
        return [
            self::PURPOSE_RENT_SERVICE,
            self::PURPOSE_RENT_SERVICE_GENERATOR,
        ];
    }

    /**
     * Outstanding balances by category, using payment allocation order:
     * water first, then electricity, then services, then rent.
     *
     * @return array{
     *     water_owed: string,
     *     electricity_owed: string,
     *     services_owed: string,
     *     rent_owed: string,
     *     total_due: string,
     *     credit_balance: string,
     *     status: 'owes'|'paid_up'|'credit',
     *     currency_code: string
     * }
     */
    public function breakdown(Tenant|int $tenant, ?int $excludePaymentId = null): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->breakdownsForTenants([$tenantId], $excludePaymentId)[$tenantId];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<int, array{
     *     water_owed: string,
     *     electricity_owed: string,
     *     services_owed: string,
     *     rent_owed: string,
     *     total_due: string,
     *     credit_balance: string,
     *     status: 'owes'|'paid_up'|'credit',
     *     currency_code: string
     * }>
     */
    public function breakdownsForTenants(array $tenantIds, ?int $excludePaymentId = null): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $chargeAggregates = $this->batchChargeAggregates($tenantIds);
        $paidTotals = $this->batchPaidTotals($tenantIds, $excludePaymentId);

        $results = [];
        foreach ($tenantIds as $tenantId) {
            $results[$tenantId] = $this->composeBreakdown(
                $chargeAggregates[$tenantId],
                $paidTotals[$tenantId] ?? '0.00',
            );
        }

        return $results;
    }

    /**
     * @param  array{
     *     water: string,
     *     electricity: string,
     *     services: string,
     *     rent: string,
     *     total_charged: string
     * }  $charges
     * @return array{
     *     water_owed: string,
     *     electricity_owed: string,
     *     services_owed: string,
     *     rent_owed: string,
     *     total_due: string,
     *     credit_balance: string,
     *     status: 'owes'|'paid_up'|'credit',
     *     currency_code: string
     * }
     */
    private function composeBreakdown(array $charges, string $paidTotal): array
    {
        $paidPool = $paidTotal;

        $waterOwed = $this->applyPayment($charges['water'], $paidPool);
        $paidPool = $this->remainingPool($charges['water'], $paidPool);

        $electricityOwed = $this->applyPayment($charges['electricity'], $paidPool);
        $paidPool = $this->remainingPool($charges['electricity'], $paidPool);

        $servicesOwed = $this->applyPayment($charges['services'], $paidPool);
        $paidPool = $this->remainingPool($charges['services'], $paidPool);

        $rentOwed = $this->applyPayment($charges['rent'], $paidPool);
        $totalDue = bcsub($charges['total_charged'], $paidTotal, 2);

        return [
            'water_owed' => $waterOwed,
            'electricity_owed' => $electricityOwed,
            'services_owed' => $servicesOwed,
            'rent_owed' => $rentOwed,
            'total_due' => $totalDue,
            'credit_balance' => bccomp($totalDue, '0', 2) < 0 ? bcmul($totalDue, '-1', 2) : '0.00',
            'status' => $this->resolveStatus($totalDue),
            'currency_code' => MoneyConfig::rentalCurrency(),
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<int, array{water: string, electricity: string, services: string, rent: string, total_charged: string}>
     */
    private function batchChargeAggregates(array $tenantIds): array
    {
        $aggregates = [];
        foreach ($tenantIds as $tenantId) {
            $aggregates[$tenantId] = [
                'water' => '0.00',
                'electricity' => '0.00',
                'services' => '0.00',
                'rent' => '0.00',
                'total_charged' => '0.00',
            ];
        }

        $rows = RentCharge::query()
            ->whereIn('tenant_id', $tenantIds)
            ->selectRaw('tenant_id, purpose, COALESCE(SUM(total_amount), 0) as total_amount, COALESCE(SUM(service_amount), 0) as service_amount, COALESCE(SUM(rent_amount), 0) as rent_amount')
            ->groupBy('tenant_id', 'purpose')
            ->get();

        foreach ($rows as $row) {
            $tenantId = (int) $row->tenant_id;
            $purpose = (string) $row->purpose;
            $aggregates[$tenantId]['total_charged'] = bcadd(
                $aggregates[$tenantId]['total_charged'],
                bcadd((string) $row->total_amount, '0', 2),
                2,
            );

            if ($purpose === self::PURPOSE_WATER) {
                $aggregates[$tenantId]['water'] = bcadd($aggregates[$tenantId]['water'], (string) $row->total_amount, 2);
            } elseif ($purpose === self::PURPOSE_ELECTRICITY) {
                $aggregates[$tenantId]['electricity'] = bcadd($aggregates[$tenantId]['electricity'], (string) $row->total_amount, 2);
            } elseif (in_array($purpose, self::rentServicePurposes(), true)) {
                $aggregates[$tenantId]['services'] = bcadd($aggregates[$tenantId]['services'], (string) $row->service_amount, 2);
                $aggregates[$tenantId]['rent'] = bcadd($aggregates[$tenantId]['rent'], (string) $row->rent_amount, 2);
            }
        }

        return $aggregates;
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<int, string>
     */
    private function batchPaidTotals(array $tenantIds, ?int $excludePaymentId): array
    {
        $paidTotals = array_fill_keys($tenantIds, '0.00');

        $paymentsQuery = RentPayment::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('status', RentPaymentStatus::Active);

        if ($excludePaymentId !== null) {
            $paymentsQuery->where('id', '!=', $excludePaymentId);
        }

        $payments = $paymentsQuery
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COALESCE(SUM(amount), 0) + COALESCE(SUM(discount), 0) as total')
            ->pluck('total', 'tenant_id');

        foreach ($tenantIds as $tenantId) {
            $paidTotals[$tenantId] = bcadd((string) ($payments[$tenantId] ?? '0'), '0', 2);
        }

        return $paidTotals;
    }

    public function totalDue(Tenant|int $tenant, ?int $excludePaymentId = null): string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->breakdownsForTenants([$tenantId], $excludePaymentId)[$tenantId]['total_due'];
    }

    public function totalCharged(int $tenantId): string
    {
        return bcadd((string) RentCharge::query()->where('tenant_id', $tenantId)->sum('total_amount'), '0', 2);
    }

    private function applyPayment(string $charged, string $paidPool): string
    {
        if (bccomp($charged, '0', 2) <= 0) {
            return '0.00';
        }

        if (bccomp($paidPool, '0', 2) <= 0) {
            return bcadd($charged, '0', 2);
        }

        if (bccomp($paidPool, $charged, 2) >= 0) {
            return '0.00';
        }

        return bcsub($charged, $paidPool, 2);
    }

    private function remainingPool(string $charged, string $paidPool): string
    {
        if (bccomp($paidPool, '0', 2) <= 0) {
            return '0.00';
        }

        if (bccomp($paidPool, $charged, 2) >= 0) {
            return bcsub($paidPool, $charged, 2);
        }

        return '0.00';
    }

    private function resolveStatus(string $totalDue): string
    {
        if (bccomp($totalDue, '0', 2) > 0) {
            return 'owes';
        }

        if (bccomp($totalDue, '0', 2) < 0) {
            return 'credit';
        }

        return 'paid_up';
    }
}
