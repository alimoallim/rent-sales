<?php

namespace App\Services\Rental;

use App\Enums\RentPaymentStatus;
use App\Models\RentCharge;
use App\Models\RentPayment;

class TenantBalanceBatchService
{
    /**
     * Outstanding total-due balances for many tenants in two aggregate queries.
     *
     * @param  list<int>  $tenantIds
     * @return array<int, string>
     */
    public function totalDueForTenants(array $tenantIds, ?int $excludePaymentId = null): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $balances = array_fill_keys($tenantIds, '0.00');

        $charges = RentCharge::query()
            ->whereIn('tenant_id', $tenantIds)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COALESCE(SUM(total_amount), 0) as total')
            ->pluck('total', 'tenant_id');

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
            $charged = bcadd((string) ($charges[$tenantId] ?? '0'), '0', 2);
            $paid = bcadd((string) ($payments[$tenantId] ?? '0'), '0', 2);

            $balances[$tenantId] = bcsub($charged, $paid, 2);
        }

        return $balances;
    }

    /**
     * @param  list<int>  $tenantIds
     * @return list<int>
     */
    public function tenantIdsWithPositiveBalance(array $tenantIds): array
    {
        $balances = $this->totalDueForTenants($tenantIds);

        return array_values(array_filter(
            $tenantIds,
            fn (int $tenantId): bool => bccomp($balances[$tenantId] ?? '0', '0', 2) > 0,
        ));
    }

    /**
     * @param  array<int, string>  $balances
     * @return array{with_balance: int, total_outstanding: string}
     */
    public function summarizePositiveBalances(array $balances): array
    {
        $withBalance = 0;
        $totalOutstanding = '0.00';

        foreach ($balances as $balance) {
            if (bccomp($balance, '0', 2) > 0) {
                $withBalance++;
                $totalOutstanding = bcadd($totalOutstanding, $balance, 2);
            }
        }

        return [
            'with_balance' => $withBalance,
            'total_outstanding' => $totalOutstanding,
        ];
    }
}
