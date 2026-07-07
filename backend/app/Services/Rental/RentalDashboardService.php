<?php

namespace App\Services\Rental;

use App\Enums\ChargeBatchStatus;
use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\ChargeBatch;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\TenantMoveOut;
use App\Support\MoneyConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RentalDashboardService
{
    public function __construct(
        private readonly TenantBalanceBreakdownService $breakdownService,
        private readonly RentalDashboardActionService $actionService,
        private readonly ChargeBatchService $chargeBatchService,
        private readonly TenantMeterReadingReminderService $meterReadingReminder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $totalUnits = RentalUnit::query()->count();
        $occupiedUnits = RentalUnit::query()->where('status', RentalUnitStatus::Occupied)->count();
        $vacantUnits = RentalUnit::query()->where('status', RentalUnitStatus::Vacant)->count();
        $activeTenants = $this->activeTenants();
        $breakdowns = $this->breakdownService->breakdownsForTenants($activeTenants->pluck('id')->all());

        $outstanding = $this->aggregateOutstandingFromBreakdowns($breakdowns);
        $collections = $this->aggregateCollections($currentMonthStart, $currentMonthEnd, $previousMonthStart, $previousMonthEnd);

        return [
            'generated_at' => $now->toISOString(),
            'currency_code' => MoneyConfig::rentalCurrency(),
            'period' => [
                'month' => (int) $now->month,
                'year' => (int) $now->year,
                'label' => $now->format('F Y'),
            ],
            'occupancy' => [
                'buildings' => RentalBuilding::query()->count(),
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'occupancy_rate' => $this->occupancyRate($occupiedUnits, $totalUnits),
                'active_tenants' => $activeTenants->count(),
            ],
            'collections' => $collections,
            'outstanding' => $outstanding,
            'utilities' => [
                'missing_water_readings' => $this->missingUtilityReadings('water', $now),
                'missing_electricity_readings' => $this->missingUtilityReadings('electricity', $now),
            ],
            'operations' => [
                'pending_charge_batches' => $this->chargeBatchService->pendingBatchCount(),
                'move_outs_last_30_days' => TenantMoveOut::query()
                    ->whereDate('moved_out_at', '>=', $now->copy()->subDays(30)->toDateString())
                    ->count(),
                'move_outs_last_90_days' => TenantMoveOut::query()
                    ->whereDate('moved_out_at', '>=', $now->copy()->subDays(90)->toDateString())
                    ->count(),
            ],
            'charges' => [
                'current_month_total' => (string) RentCharge::query()
                    ->where('billing_month', $now->month)
                    ->where('billing_year', $now->year)
                    ->sum('total_amount'),
                'current_month_count' => RentCharge::query()
                    ->where('billing_month', $now->month)
                    ->where('billing_year', $now->year)
                    ->count(),
            ],
            'top_debtors' => $this->topDebtorsFromBreakdowns($activeTenants, $breakdowns),
            'recent_payments' => $this->recentPayments(),
            'recent_move_outs' => $this->recentMoveOuts(),
            'building_summary' => $this->buildingSummaryFromBreakdowns($activeTenants, $breakdowns),
            'action_required' => $this->actionService->build(
                (int) $now->month,
                (int) $now->year,
                $now->format('F Y'),
            ),
        ];
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function activeTenants(): Collection
    {
        return Tenant::query()
            ->with(['building', 'unit'])
            ->where('status', TenantStatus::Active)
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $breakdowns
     * @return array<string, mixed>
     */
    private function aggregateOutstandingFromBreakdowns(array $breakdowns): array
    {
        $totals = [
            'total_balance' => '0.00',
            'rent_owed' => '0.00',
            'services_owed' => '0.00',
            'water_owed' => '0.00',
            'electricity_owed' => '0.00',
            'tenants_with_balance' => 0,
            'tenants_paid_up' => 0,
            'tenants_in_credit' => 0,
        ];

        foreach ($breakdowns as $breakdown) {
            match ($breakdown['status']) {
                'owes' => $totals['tenants_with_balance']++,
                'credit' => $totals['tenants_in_credit']++,
                default => $totals['tenants_paid_up']++,
            };

            if (bccomp($breakdown['total_due'], '0', 2) <= 0) {
                continue;
            }

            $totals['total_balance'] = bcadd($totals['total_balance'], $breakdown['total_due'], 2);
            $totals['rent_owed'] = bcadd($totals['rent_owed'], $breakdown['rent_owed'], 2);
            $totals['services_owed'] = bcadd($totals['services_owed'], $breakdown['services_owed'], 2);
            $totals['water_owed'] = bcadd($totals['water_owed'], $breakdown['water_owed'], 2);
            $totals['electricity_owed'] = bcadd($totals['electricity_owed'], $breakdown['electricity_owed'], 2);
        }

        return array_merge($totals, [
            'currency_code' => MoneyConfig::rentalCurrency(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateCollections(
        Carbon $currentStart,
        Carbon $currentEnd,
        Carbon $previousStart,
        Carbon $previousEnd,
    ): array {
        $currentAmount = (string) RentPayment::query()
            ->where('status', RentPaymentStatus::Active)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->sum('amount');

        $previousAmount = (string) RentPayment::query()
            ->where('status', RentPaymentStatus::Active)
            ->whereBetween('paid_at', [$previousStart, $previousEnd])
            ->sum('amount');

        $currentCount = RentPayment::query()
            ->where('status', RentPaymentStatus::Active)
            ->whereBetween('paid_at', [$currentStart, $currentEnd])
            ->count();

        $changePercent = null;
        if (bccomp($previousAmount, '0', 2) > 0) {
            $delta = bcsub($currentAmount, $previousAmount, 2);
            $changePercent = round((float) bcmul(bcdiv($delta, $previousAmount, 4), '100', 2), 1);
        }

        return [
            'current_month' => $currentAmount,
            'previous_month' => $previousAmount,
            'change_percent' => $changePercent,
            'payment_count_current_month' => $currentCount,
            'current_month_label' => $currentStart->format('F Y'),
            'previous_month_label' => $previousStart->format('F Y'),
        ];
    }

    /**
     * @return array{count: int}
     */
    private function missingUtilityReadings(string $utility, Carbon $now): array
    {
        $count = 0;
        $month = (int) $now->month;
        $year = (int) $now->year;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->when(
                $utility === 'water',
                fn ($query) => $query->where('requires_water_metering', true),
                fn ($query) => $query->where('requires_electricity_metering', true),
            )
            ->each(function (Tenant $tenant) use (&$count, $utility, $month, $year): void {
                foreach ($this->meterReadingReminder->missingRequiredReadings($tenant, $month, $year) as $missing) {
                    if ($missing['utility'] === $utility) {
                        $count++;
                    }
                }
            });

        return ['count' => $count];
    }

    /**
     * @param  Collection<int, Tenant>  $activeTenants
     * @param  array<int, array<string, mixed>>  $breakdowns
     * @return list<array<string, mixed>>
     */
    private function topDebtorsFromBreakdowns(Collection $activeTenants, array $breakdowns, int $limit = 8): array
    {
        $debtors = [];

        foreach ($activeTenants as $tenant) {
            $breakdown = $breakdowns[$tenant->id] ?? null;
            if ($breakdown === null || bccomp($breakdown['total_due'], '0', 2) <= 0) {
                continue;
            }

            $debtors[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'building_name' => $tenant->building?->name,
                'unit_label' => $tenant->unit?->house_number,
                'balance' => $breakdown['total_due'],
                'rent_owed' => $breakdown['rent_owed'],
                'services_owed' => $breakdown['services_owed'],
                'water_owed' => $breakdown['water_owed'],
                'electricity_owed' => $breakdown['electricity_owed'],
            ];
        }

        usort($debtors, fn (array $a, array $b) => bccomp($b['balance'], $a['balance'], 2));

        return array_slice($debtors, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPayments(int $limit = 8): array
    {
        return RentPayment::query()
            ->with(['tenant', 'building'])
            ->where('status', RentPaymentStatus::Active)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (RentPayment $payment) => [
                'payment_id' => $payment->id,
                'paid_at' => $payment->paid_at?->toDateString(),
                'tenant_id' => $payment->tenant_id,
                'tenant_name' => $payment->tenant?->name,
                'building_name' => $payment->building?->name,
                'amount' => $payment->amount,
                'discount' => $payment->discount,
                'invoice_reference' => $payment->invoice_reference,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentMoveOuts(int $limit = 5): array
    {
        return TenantMoveOut::query()
            ->with(['tenant', 'building', 'unit'])
            ->orderByDesc('moved_out_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (TenantMoveOut $moveOut) => [
                'id' => $moveOut->id,
                'tenant_name' => $moveOut->tenant?->name,
                'building_name' => $moveOut->building?->name,
                'unit_label' => $moveOut->unit?->house_number,
                'moved_out_at' => $moveOut->moved_out_at?->toDateString(),
                'refund_amount' => $moveOut->refund_amount,
                'reason' => $moveOut->reason,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Tenant>  $activeTenants
     * @param  array<int, array<string, mixed>>  $breakdowns
     * @return list<array<string, mixed>>
     */
    private function buildingSummaryFromBreakdowns(Collection $activeTenants, array $breakdowns): array
    {
        $buildings = RentalBuilding::query()->orderBy('name')->get();
        $outstandingByBuilding = [];

        foreach ($activeTenants as $tenant) {
            $breakdown = $breakdowns[$tenant->id] ?? null;
            if ($breakdown === null || bccomp($breakdown['total_due'], '0', 2) <= 0) {
                continue;
            }

            $buildingId = $tenant->rental_building_id;
            $outstandingByBuilding[$buildingId] = bcadd(
                $outstandingByBuilding[$buildingId] ?? '0.00',
                $breakdown['total_due'],
                2,
            );
        }

        $summary = [];

        foreach ($buildings as $building) {
            $totalUnits = RentalUnit::query()
                ->where('rental_building_id', $building->id)
                ->count();
            $occupiedUnits = RentalUnit::query()
                ->where('rental_building_id', $building->id)
                ->where('status', RentalUnitStatus::Occupied)
                ->count();
            $vacantUnits = RentalUnit::query()
                ->where('rental_building_id', $building->id)
                ->where('status', RentalUnitStatus::Vacant)
                ->count();
            $activeTenantCount = $activeTenants
                ->where('rental_building_id', $building->id)
                ->count();

            $summary[] = [
                'building_id' => $building->id,
                'building_name' => $building->name,
                'active_tenants' => $activeTenantCount,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'occupancy_rate' => $this->occupancyRate($occupiedUnits, $totalUnits),
                'outstanding_balance' => $outstandingByBuilding[$building->id] ?? '0.00',
            ];
        }

        usort($summary, fn (array $a, array $b) => bccomp($b['outstanding_balance'], $a['outstanding_balance'], 2));

        return $summary;
    }

    private function occupancyRate(int $occupied, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($occupied / $total) * 100, 1);
    }
}
