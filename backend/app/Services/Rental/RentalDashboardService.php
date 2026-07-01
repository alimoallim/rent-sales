<?php

namespace App\Services\Rental;

use App\Enums\ChargeBatchStatus;
use App\Enums\ElectricityBillStatus;
use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Enums\WaterBillStatus;
use App\Models\ChargeBatch;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\TenantElectricityBill;
use App\Models\TenantMoveOut;
use App\Models\TenantWaterBill;
use Illuminate\Support\Carbon;

class RentalDashboardService
{
    public function __construct(
        private readonly TenantBalanceBreakdownService $breakdownService,
        private readonly RentalDashboardActionService $actionService,
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
        $activeTenants = Tenant::query()->where('status', TenantStatus::Active)->count();

        $outstanding = $this->aggregateOutstanding();
        $collections = $this->aggregateCollections($currentMonthStart, $currentMonthEnd, $previousMonthStart, $previousMonthEnd);

        return [
            'generated_at' => $now->toISOString(),
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
                'active_tenants' => $activeTenants,
            ],
            'collections' => $collections,
            'outstanding' => $outstanding,
            'utilities' => [
                'pending_water_bills' => $this->pendingUtilityBills(TenantWaterBill::class, WaterBillStatus::Pending),
                'pending_electricity_bills' => $this->pendingUtilityBills(TenantElectricityBill::class, ElectricityBillStatus::Pending),
            ],
            'operations' => [
                'pending_charge_batches' => ChargeBatch::query()
                    ->whereIn('status', [ChargeBatchStatus::Draft, ChargeBatchStatus::PartiallyApproved])
                    ->count(),
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
            'top_debtors' => $this->topDebtors(),
            'recent_payments' => $this->recentPayments(),
            'recent_move_outs' => $this->recentMoveOuts(),
            'building_summary' => $this->buildingSummary(),
            'action_required' => $this->actionService->build(
                (int) $now->month,
                (int) $now->year,
                $now->format('F Y'),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateOutstanding(): array
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

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->each(function (Tenant $tenant) use (&$totals): void {
                $breakdown = $this->breakdownService->breakdown($tenant);

                match ($breakdown['status']) {
                    'owes' => $totals['tenants_with_balance']++,
                    'credit' => $totals['tenants_in_credit']++,
                    default => $totals['tenants_paid_up']++,
                };

                if (bccomp($breakdown['total_due'], '0', 2) <= 0) {
                    return;
                }

                $totals['total_balance'] = bcadd($totals['total_balance'], $breakdown['total_due'], 2);
                $totals['rent_owed'] = bcadd($totals['rent_owed'], $breakdown['rent_owed'], 2);
                $totals['services_owed'] = bcadd($totals['services_owed'], $breakdown['services_owed'], 2);
                $totals['water_owed'] = bcadd($totals['water_owed'], $breakdown['water_owed'], 2);
                $totals['electricity_owed'] = bcadd($totals['electricity_owed'], $breakdown['electricity_owed'], 2);
            });

        return $totals;
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
     * @param  class-string  $modelClass
     * @return array{count: int, amount: string}
     */
    private function pendingUtilityBills(string $modelClass, WaterBillStatus|ElectricityBillStatus $status): array
    {
        return [
            'count' => $modelClass::query()->where('status', $status)->count(),
            'amount' => (string) $modelClass::query()->where('status', $status)->sum('amount'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topDebtors(int $limit = 8): array
    {
        $debtors = [];

        Tenant::query()
            ->with(['building', 'unit'])
            ->where('status', TenantStatus::Active)
            ->each(function (Tenant $tenant) use (&$debtors): void {
                $breakdown = $this->breakdownService->breakdown($tenant);

                if (bccomp($breakdown['total_due'], '0', 2) <= 0) {
                    return;
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
            });

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
     * @return list<array<string, mixed>>
     */
    private function buildingSummary(): array
    {
        $buildings = RentalBuilding::query()->orderBy('name')->get();
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
            $activeTenants = Tenant::query()
                ->where('rental_building_id', $building->id)
                ->where('status', TenantStatus::Active)
                ->count();

            $outstanding = '0.00';
            Tenant::query()
                ->where('rental_building_id', $building->id)
                ->where('status', TenantStatus::Active)
                ->each(function (Tenant $tenant) use (&$outstanding): void {
                    $due = $this->breakdownService->totalDue($tenant);
                    if (bccomp($due, '0', 2) > 0) {
                        $outstanding = bcadd($outstanding, $due, 2);
                    }
                });

            $summary[] = [
                'building_id' => $building->id,
                'building_name' => $building->name,
                'active_tenants' => $activeTenants,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'occupancy_rate' => $this->occupancyRate($occupiedUnits, $totalUnits),
                'outstanding_balance' => $outstanding,
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
