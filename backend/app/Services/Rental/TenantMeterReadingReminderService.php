<?php

namespace App\Services\Rental;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use Illuminate\Support\Carbon;

class TenantMeterReadingReminderService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function missingRequiredReadings(Tenant $tenant, ?int $billingMonth = null, ?int $billingYear = null): array
    {
        if ($tenant->status === TenantStatus::Inactive) {
            return [];
        }

        $now = Carbon::now();
        $month = $billingMonth ?? (int) $now->month;
        $year = $billingYear ?? (int) $now->year;
        $periodLabel = Carbon::create($year, $month, 1)->format('F Y');

        $missing = [];

        if ($tenant->requires_water_metering && ! $this->hasWaterReadingForPeriod($tenant->id, $month, $year)) {
            $missing[] = $this->buildWaterReminder($tenant, $month, $year, $periodLabel);
        }

        if ($tenant->requires_electricity_metering && ! $this->hasElectricityReadingForPeriod($tenant->id, $month, $year)) {
            $missing[] = $this->buildElectricityReminder($tenant, $month, $year, $periodLabel);
        }

        return $missing;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function remindersForTenant(Tenant $tenant, ?int $billingMonth = null, ?int $billingYear = null): array
    {
        return $this->missingRequiredReadings($tenant, $billingMonth, $billingYear);
    }

    public function paymentBlocked(Tenant $tenant, ?int $billingMonth = null, ?int $billingYear = null): bool
    {
        return $this->missingRequiredReadings($tenant, $billingMonth, $billingYear) !== [];
    }

    private function hasWaterReadingForPeriod(int $tenantId, int $month, int $year): bool
    {
        return TenantWaterBill::query()
            ->where('tenant_id', $tenantId)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->exists();
    }

    private function hasElectricityReadingForPeriod(int $tenantId, int $month, int $year): bool
    {
        return TenantElectricityBill::query()
            ->where('tenant_id', $tenantId)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWaterReminder(Tenant $tenant, int $month, int $year, string $periodLabel): array
    {
        return [
            'utility' => 'water',
            'utility_label' => 'Water',
            'billing_month' => $month,
            'billing_year' => $year,
            'period_label' => $periodLabel,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'rental_building_id' => $tenant->rental_building_id,
            'blocks_payment' => true,
            'can_record_reading' => true,
            'message' => sprintf(
                '%s\'s agreement requires a water meter reading for %s, but none has been recorded yet. Enter the water reading before collecting payment.',
                $tenant->name,
                $periodLabel,
            ),
            'action_label' => 'Enter water reading',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildElectricityReminder(Tenant $tenant, int $month, int $year, string $periodLabel): array
    {
        return [
            'utility' => 'electricity',
            'utility_label' => 'Electricity',
            'billing_month' => $month,
            'billing_year' => $year,
            'period_label' => $periodLabel,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'rental_building_id' => $tenant->rental_building_id,
            'blocks_payment' => true,
            'can_record_reading' => true,
            'message' => sprintf(
                '%s\'s agreement requires an electricity meter reading for %s, but none has been recorded yet. Enter the electricity reading before collecting payment.',
                $tenant->name,
                $periodLabel,
            ),
            'action_label' => 'Enter electricity reading',
        ];
    }
}
