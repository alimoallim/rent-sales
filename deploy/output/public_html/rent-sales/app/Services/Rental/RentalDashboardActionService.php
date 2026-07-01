<?php

namespace App\Services\Rental;

use App\Enums\ChargeBatchItemStatus;
use App\Enums\ChargeBatchStatus;
use App\Enums\ElectricityBillStatus;
use App\Enums\TenantStatus;
use App\Enums\WaterBillStatus;
use App\Models\ChargeBatch;
use App\Models\ChargeBatchItem;
use App\Models\RentalBuilding;
use App\Models\Tenant;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use Illuminate\Support\Carbon;

class RentalDashboardActionService
{
    public function __construct(
        private readonly TenantMeterReadingReminderService $meterReadingReminder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $billingMonth, int $billingYear, string $periodLabel): array
    {
        $items = [];

        $items = array_merge($items, $this->openChargeBatchActions());
        $items = array_merge($items, $this->missingMeterReadingActions($billingMonth, $billingYear, $periodLabel));
        $items = array_merge($items, $this->buildingsWithoutBatchActions($billingMonth, $billingYear, $periodLabel));
        $items = array_merge($items, $this->unpaidUtilityBillActions());

        usort($items, fn (array $a, array $b) => [$this->severityRank($b['severity']), $a['title']] <=> [$this->severityRank($a['severity']), $b['title']]);

        $highPriority = array_filter($items, fn (array $item) => $item['severity'] === 'high');

        return [
            'total_count' => count($items),
            'high_priority_count' => count($highPriority),
            'items' => array_slice($items, 0, 20),
            'categories' => $this->groupByCategory($items),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openChargeBatchActions(): array
    {
        $actions = [];

        $batches = ChargeBatch::query()
            ->with('building')
            ->whereIn('status', [ChargeBatchStatus::Draft, ChargeBatchStatus::PartiallyApproved])
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->get();

        foreach ($batches as $batch) {
            $periodLabel = Carbon::create($batch->billing_year, $batch->billing_month, 1)->format('F Y');
            $buildingName = $batch->building?->name ?? 'Building';
            $query = $this->chargeBatchQuery($batch);

            $pendingItems = ChargeBatchItem::query()
                ->where('charge_batch_id', $batch->id)
                ->where('item_status', ChargeBatchItemStatus::Pending)
                ->count();

            if ($pendingItems > 0) {
                $actions[] = $this->makeItem(
                    id: "batch-pending-{$batch->id}",
                    type: 'charge_batch_pending_readings',
                    category: 'charge_approvals',
                    severity: 'high',
                    title: 'Charge batch blocked by missing readings',
                    description: "{$buildingName} · {$periodLabel} — {$pendingItems} utility line(s) need meter readings before approval",
                    count: $pendingItems,
                    actionPath: '/rental/charge-batches',
                    actionLabel: 'Review batch',
                    actionQuery: $query,
                );
            }

            $readyTenants = $this->countTenantsReadyForApproval($batch->id);

            if ($readyTenants > 0) {
                $actions[] = $this->makeItem(
                    id: "batch-approve-{$batch->id}",
                    type: 'charge_batch_awaiting_approval',
                    category: 'charge_approvals',
                    severity: 'medium',
                    title: 'Charges ready for approval',
                    description: "{$buildingName} · {$periodLabel} — {$readyTenants} tenant(s) can be approved and posted",
                    count: $readyTenants,
                    actionPath: '/rental/charge-batches',
                    actionLabel: 'Approve charges',
                    actionQuery: $query,
                );
            }

            if ($pendingItems === 0 && $readyTenants === 0 && $batch->status === ChargeBatchStatus::Draft) {
                $unresolved = $this->countUnresolvedTenants($batch->id);
                if ($unresolved > 0) {
                    $actions[] = $this->makeItem(
                        id: "batch-review-{$batch->id}",
                        type: 'charge_batch_needs_review',
                        category: 'charge_approvals',
                        severity: 'medium',
                        title: 'Charge batch needs review',
                        description: "{$buildingName} · {$periodLabel} — draft batch still open for {$unresolved} tenant(s)",
                        count: $unresolved,
                        actionPath: '/rental/charge-batches',
                        actionLabel: 'Open batch',
                        actionQuery: $query,
                    );
                }
            }
        }

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missingMeterReadingActions(int $billingMonth, int $billingYear, string $periodLabel): array
    {
        $actions = [];
        $seen = [];

        Tenant::query()
            ->with(['building', 'unit'])
            ->where('status', TenantStatus::Active)
            ->where(function ($query): void {
                $query->where('requires_water_metering', true)
                    ->orWhere('requires_electricity_metering', true);
            })
            ->orderBy('name')
            ->each(function (Tenant $tenant) use (&$actions, &$seen, $billingMonth, $billingYear, $periodLabel): void {
                foreach ($this->meterReadingReminder->missingRequiredReadings($tenant, $billingMonth, $billingYear) as $missing) {
                    $key = "{$tenant->id}-{$missing['utility']}";
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $utilityLabel = $missing['utility_label'];
                    $unitLabel = $tenant->unit?->house_number;
                    $buildingName = $tenant->building?->name ?? 'Building';
                    $location = $unitLabel ? "{$buildingName} · Unit {$unitLabel}" : $buildingName;

                    $actions[] = $this->makeItem(
                        id: "reading-{$key}",
                        type: 'missing_meter_reading',
                        category: 'missing_readings',
                        severity: 'high',
                        title: "Missing {$utilityLabel} reading",
                        description: "{$tenant->name} ({$location}) — contract requires {$utilityLabel} meter reading for {$periodLabel}",
                        count: 1,
                        actionPath: $missing['utility'] === 'water' ? '/rental/water-bills' : '/rental/electricity-bills',
                        actionLabel: $missing['action_label'],
                        actionQuery: [
                            'tenant_id' => $tenant->id,
                            'building_id' => $tenant->rental_building_id,
                            'billing_month' => $billingMonth,
                            'billing_year' => $billingYear,
                        ],
                        meta: [
                            'tenant_id' => $tenant->id,
                            'tenant_name' => $tenant->name,
                            'utility' => $missing['utility'],
                        ],
                    );
                }
            });

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildingsWithoutBatchActions(int $billingMonth, int $billingYear, string $periodLabel): array
    {
        $actions = [];

        RentalBuilding::query()
            ->whereHas('tenants', fn ($query) => $query->where('status', TenantStatus::Active))
            ->orderBy('name')
            ->each(function (RentalBuilding $building) use (&$actions, $billingMonth, $billingYear, $periodLabel): void {
                $hasBatch = ChargeBatch::query()
                    ->where('rental_building_id', $building->id)
                    ->where('billing_month', $billingMonth)
                    ->where('billing_year', $billingYear)
                    ->exists();

                if ($hasBatch) {
                    return;
                }

                $tenantCount = Tenant::query()
                    ->where('rental_building_id', $building->id)
                    ->where('status', TenantStatus::Active)
                    ->count();

                $actions[] = $this->makeItem(
                    id: "no-batch-{$building->id}-{$billingYear}-{$billingMonth}",
                    type: 'building_missing_charge_batch',
                    category: 'billing_setup',
                    severity: 'medium',
                    title: 'Monthly charges not generated',
                    description: "{$building->name} · {$periodLabel} — no charge batch yet for {$tenantCount} active tenant(s)",
                    count: $tenantCount,
                    actionPath: '/rental/charge-batches',
                    actionLabel: 'Generate batch',
                    actionQuery: [
                        'building_id' => $building->id,
                        'billing_month' => $billingMonth,
                        'billing_year' => $billingYear,
                    ],
                );
            });

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unpaidUtilityBillActions(): array
    {
        $actions = [];

        $pendingWater = TenantWaterBill::query()->where('status', WaterBillStatus::Pending)->count();
        if ($pendingWater > 0) {
            $amount = (string) TenantWaterBill::query()->where('status', WaterBillStatus::Pending)->sum('amount');
            $actions[] = $this->makeItem(
                id: 'unpaid-water-bills',
                type: 'unpaid_water_bills',
                category: 'utility_collections',
                severity: 'low',
                title: 'Water bills awaiting payment',
                description: "{$pendingWater} water bill(s) recorded but not yet marked paid — KES {$amount} total",
                count: $pendingWater,
                actionPath: '/rental/water-bills',
                actionLabel: 'View water bills',
            );
        }

        $pendingElectricity = TenantElectricityBill::query()->where('status', ElectricityBillStatus::Pending)->count();
        if ($pendingElectricity > 0) {
            $amount = (string) TenantElectricityBill::query()->where('status', ElectricityBillStatus::Pending)->sum('amount');
            $actions[] = $this->makeItem(
                id: 'unpaid-electricity-bills',
                type: 'unpaid_electricity_bills',
                category: 'utility_collections',
                severity: 'low',
                title: 'Electricity bills awaiting payment',
                description: "{$pendingElectricity} electricity bill(s) recorded but not yet marked paid — KES {$amount} total",
                count: $pendingElectricity,
                actionPath: '/rental/electricity-bills',
                actionLabel: 'View electricity bills',
            );
        }

        return $actions;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function groupByCategory(array $items): array
    {
        $labels = [
            'charge_approvals' => 'Charge approvals',
            'missing_readings' => 'Missing meter readings',
            'billing_setup' => 'Billing setup',
            'utility_collections' => 'Utility collections',
        ];

        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item['category']][] = $item;
        }

        $categories = [];
        foreach ($labels as $key => $label) {
            if (empty($grouped[$key])) {
                continue;
            }

            $categories[] = [
                'key' => $key,
                'label' => $label,
                'count' => count($grouped[$key]),
                'items' => array_slice($grouped[$key], 0, 6),
            ];
        }

        return $categories;
    }

    private function countTenantsReadyForApproval(int $batchId): int
    {
        $tenantIds = ChargeBatchItem::query()
            ->where('charge_batch_id', $batchId)
            ->distinct()
            ->pluck('tenant_id');

        $ready = 0;
        foreach ($tenantIds as $tenantId) {
            if ($this->tenantIsExcluded($batchId, (int) $tenantId)) {
                continue;
            }

            $hasPending = ChargeBatchItem::query()
                ->where('charge_batch_id', $batchId)
                ->where('tenant_id', $tenantId)
                ->where('item_status', ChargeBatchItemStatus::Pending)
                ->exists();

            if ($hasPending) {
                continue;
            }

            $hasDraft = ChargeBatchItem::query()
                ->where('charge_batch_id', $batchId)
                ->where('tenant_id', $tenantId)
                ->where('item_status', ChargeBatchItemStatus::Draft)
                ->exists();

            if ($hasDraft) {
                $ready++;
            }
        }

        return $ready;
    }

    private function countUnresolvedTenants(int $batchId): int
    {
        $tenantIds = ChargeBatchItem::query()
            ->where('charge_batch_id', $batchId)
            ->distinct()
            ->pluck('tenant_id');

        $count = 0;
        foreach ($tenantIds as $tenantId) {
            if ($this->tenantIsExcluded($batchId, (int) $tenantId)) {
                continue;
            }

            $unresolved = ChargeBatchItem::query()
                ->where('charge_batch_id', $batchId)
                ->where('tenant_id', $tenantId)
                ->whereIn('item_status', [ChargeBatchItemStatus::Draft, ChargeBatchItemStatus::Pending])
                ->exists();

            if ($unresolved) {
                $count++;
            }
        }

        return $count;
    }

    private function tenantIsExcluded(int $batchId, int $tenantId): bool
    {
        $items = ChargeBatchItem::query()
            ->where('charge_batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->get();

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(fn (ChargeBatchItem $item) => $item->item_status === ChargeBatchItemStatus::Excluded);
    }

    /**
     * @return array{building_id: int, billing_month: int, billing_year: int}
     */
    private function chargeBatchQuery(ChargeBatch $batch): array
    {
        return [
            'building_id' => $batch->rental_building_id,
            'billing_month' => $batch->billing_month,
            'billing_year' => $batch->billing_year,
        ];
    }

    /**
     * @param  array<string, mixed>  $actionQuery
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>
     */
    private function makeItem(
        string $id,
        string $type,
        string $category,
        string $severity,
        string $title,
        string $description,
        int $count,
        string $actionPath,
        string $actionLabel,
        array $actionQuery = [],
        ?array $meta = null,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'category' => $category,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'count' => $count,
            'action_path' => $actionPath,
            'action_label' => $actionLabel,
            'action_query' => $actionQuery === [] ? null : $actionQuery,
            'meta' => $meta,
        ];
    }

    private function severityRank(string $severity): int
    {
        return match ($severity) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }
}
