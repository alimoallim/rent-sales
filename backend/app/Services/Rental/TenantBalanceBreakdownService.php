<?php

namespace App\Services\Rental;

use App\Enums\RentPaymentStatus;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;

class TenantBalanceBreakdownService
{
    public const PURPOSE_RENT_SERVICE = 'Rent + service';

    public const PURPOSE_WATER = 'Water';

    public const PURPOSE_ELECTRICITY = 'Electricity';

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
     *     status: 'owes'|'paid_up'|'credit'
     * }
     */
    public function breakdown(Tenant|int $tenant, ?int $excludePaymentId = null): array
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $waterCharged = $this->sumWaterCharges($tenantId);
        $electricityCharged = $this->sumElectricityCharges($tenantId);
        $servicesCharged = $this->sumServiceCharges($tenantId);
        $rentCharged = $this->sumRentCharges($tenantId);

        $paidPool = $this->sumActivePayments($tenantId, $excludePaymentId);

        $waterOwed = $this->applyPayment($waterCharged, $paidPool);
        $paidPool = $this->remainingPool($waterCharged, $paidPool);

        $electricityOwed = $this->applyPayment($electricityCharged, $paidPool);
        $paidPool = $this->remainingPool($electricityCharged, $paidPool);

        $servicesOwed = $this->applyPayment($servicesCharged, $paidPool);
        $paidPool = $this->remainingPool($servicesCharged, $paidPool);

        $rentOwed = $this->applyPayment($rentCharged, $paidPool);

        $chargedTotal = bcadd(
            bcadd(bcadd($waterCharged, $electricityCharged, 2), $servicesCharged, 2),
            $rentCharged,
            2,
        );
        $totalDue = bcsub($chargedTotal, $this->sumActivePayments($tenantId, $excludePaymentId), 2);

        return [
            'water_owed' => $waterOwed,
            'electricity_owed' => $electricityOwed,
            'services_owed' => $servicesOwed,
            'rent_owed' => $rentOwed,
            'total_due' => $totalDue,
            'credit_balance' => bccomp($totalDue, '0', 2) < 0 ? bcmul($totalDue, '-1', 2) : '0.00',
            'status' => $this->resolveStatus($totalDue),
        ];
    }

    public function totalDue(Tenant|int $tenant, ?int $excludePaymentId = null): string
    {
        return $this->breakdown($tenant, $excludePaymentId)['total_due'];
    }

    private function sumWaterCharges(int $tenantId): string
    {
        return (string) RentCharge::query()
            ->where('tenant_id', $tenantId)
            ->where('purpose', self::PURPOSE_WATER)
            ->sum('total_amount');
    }

    private function sumElectricityCharges(int $tenantId): string
    {
        return (string) RentCharge::query()
            ->where('tenant_id', $tenantId)
            ->where('purpose', self::PURPOSE_ELECTRICITY)
            ->sum('total_amount');
    }

    private function sumServiceCharges(int $tenantId): string
    {
        return (string) RentCharge::query()
            ->where('tenant_id', $tenantId)
            ->where('purpose', self::PURPOSE_RENT_SERVICE)
            ->sum('service_amount');
    }

    private function sumRentCharges(int $tenantId): string
    {
        return (string) RentCharge::query()
            ->where('tenant_id', $tenantId)
            ->where('purpose', self::PURPOSE_RENT_SERVICE)
            ->sum('rent_amount');
    }

    private function sumActivePayments(int $tenantId, ?int $excludePaymentId): string
    {
        $amount = (string) RentPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', RentPaymentStatus::Active)
            ->when($excludePaymentId, fn ($q) => $q->where('id', '!=', $excludePaymentId))
            ->sum('amount');

        $discount = (string) RentPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', RentPaymentStatus::Active)
            ->when($excludePaymentId, fn ($q) => $q->where('id', '!=', $excludePaymentId))
            ->sum('discount');

        return bcadd($amount, $discount, 2);
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
