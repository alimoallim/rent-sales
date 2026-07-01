<?php

namespace App\Services\Rental;

use App\Enums\ChargeBatchItemStatus;
use App\Enums\ChargeBatchItemType;
use App\Enums\ChargeBatchStatus;
use App\Enums\TenantStatus;
use App\Models\ChargeBatch;
use App\Models\ChargeBatchItem;
use App\Models\RentalBuilding;
use App\Models\Tenant;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChargeBatchService
{
    public function __construct(
        private readonly ChargeBatchPostingService $postingService,
    ) {}

    public function findForPeriod(int $buildingId, int $month, int $year): ?ChargeBatch
    {
        return ChargeBatch::query()
            ->with(['building', 'items.tenant.unit', 'items.approvedByUser', 'items.adjustedByUser'])
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->first();
    }

    public function pendingBatchCount(): int
    {
        return ChargeBatch::query()
            ->whereIn('status', [ChargeBatchStatus::Draft, ChargeBatchStatus::PartiallyApproved])
            ->count();
    }

    public function generateDraft(int $buildingId, int $month, int $year, User $user): ChargeBatch
    {
        RentalBuilding::query()->findOrFail($buildingId);

        $existing = ChargeBatch::query()
            ->where('rental_building_id', $buildingId)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'billing_month' => ['A charge batch already exists for this building and period.'],
            ]);
        }

        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

        return DB::transaction(function () use ($buildingId, $month, $year, $user, $periodEnd): ChargeBatch {
            $batch = ChargeBatch::query()->create([
                'rental_building_id' => $buildingId,
                'billing_month' => $month,
                'billing_year' => $year,
                'status' => ChargeBatchStatus::Draft,
                'generated_by' => $user->id,
                'generated_at' => now(),
            ]);

            $tenants = Tenant::query()
                ->with('unit')
                ->where('rental_building_id', $buildingId)
                ->where('status', TenantStatus::Active)
                ->where(function ($query) use ($periodEnd): void {
                    $query->whereNull('start_date')
                        ->orWhereDate('start_date', '<=', $periodEnd);
                })
                ->get();

            foreach ($tenants as $tenant) {
                $this->createItemsForTenant($batch, $tenant, $month, $year);
            }

            return $batch->fresh(['building', 'items.tenant.unit']);
        });
    }

    public function refreshPendingItems(ChargeBatch $batch): ChargeBatch
    {
        $this->assertEditable($batch);

        $batch->load(['items.tenant']);

        foreach ($batch->items as $item) {
            if ($item->item_status !== ChargeBatchItemStatus::Pending) {
                continue;
            }

            if ($item->charge_type === ChargeBatchItemType::Water) {
                $this->refreshWaterItem($item, $batch->billing_month, $batch->billing_year);
            }

            if ($item->charge_type === ChargeBatchItemType::Electricity) {
                $this->refreshElectricityItem($item, $batch->billing_month, $batch->billing_year);
            }
        }

        return $batch->fresh(['building', 'items.tenant.unit', 'items.approvedByUser', 'items.adjustedByUser']);
    }

    public function updateItemAmount(
        ChargeBatch $batch,
        ChargeBatchItem $item,
        string $amount,
        ?string $note,
        User $user,
    ): ChargeBatchItem {
        $this->assertEditable($batch);

        if ($item->charge_batch_id !== $batch->id) {
            abort(404);
        }

        if ($item->item_status === ChargeBatchItemStatus::Approved) {
            throw ValidationException::withMessages([
                'amount' => ['Approved line items cannot be edited.'],
            ]);
        }

        if ($item->item_status === ChargeBatchItemStatus::Excluded) {
            throw ValidationException::withMessages([
                'amount' => ['Excluded line items cannot be edited.'],
            ]);
        }

        $item->update([
            'amount' => $amount,
            'item_status' => ChargeBatchItemStatus::Draft,
            'pending_reason' => null,
            'manually_adjusted' => bccomp($amount, (string) ($item->source_amount ?? $amount), 2) !== 0,
            'adjusted_by' => $user->id,
            'adjusted_at' => now(),
            'adjustment_note' => $note,
        ]);

        return $item->fresh(['tenant', 'approvedByUser', 'adjustedByUser']);
    }

    public function excludeTenant(ChargeBatch $batch, int $tenantId, string $reason, User $user): ChargeBatch
    {
        $this->assertEditable($batch);

        ChargeBatchItem::query()
            ->where('charge_batch_id', $batch->id)
            ->where('tenant_id', $tenantId)
            ->whereIn('item_status', [ChargeBatchItemStatus::Draft, ChargeBatchItemStatus::Pending])
            ->update([
                'item_status' => ChargeBatchItemStatus::Excluded,
                'exclusion_reason' => $reason,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

        $this->syncBatchStatus($batch, $user);

        return $batch->fresh(['building', 'items.tenant.unit', 'items.approvedByUser', 'items.adjustedByUser']);
    }

    public function approveTenant(ChargeBatch $batch, int $tenantId, User $user): ChargeBatch
    {
        $this->assertEditable($batch);

        $this->postingService->postTenantItems($batch, $tenantId, $user);
        $this->syncBatchStatus($batch, $user);

        return $batch->fresh(['building', 'items.tenant.unit', 'items.approvedByUser', 'items.adjustedByUser']);
    }

    /**
     * @return array{approved_tenants: int, posted_total: string}
     */
    public function approveAll(ChargeBatch $batch, User $user): array
    {
        $this->assertEditable($batch);

        return DB::transaction(function () use ($batch, $user): array {
            $tenantIds = $this->unresolvedTenantIds($batch);
            $approved = 0;
            $postedTotal = '0.00';

            foreach ($tenantIds as $tenantId) {
                if ($this->tenantIsExcluded($batch->id, $tenantId)) {
                    continue;
                }

                $posted = $this->postingService->postTenantItems($batch, $tenantId, $user);
                if ($posted !== []) {
                    $approved++;
                    foreach ($posted as $charge) {
                        $postedTotal = bcadd($postedTotal, (string) $charge->total_amount, 2);
                    }
                }
            }

            $this->syncBatchStatus($batch, $user);

            return [
                'approved_tenants' => $approved,
                'posted_total' => $postedTotal,
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function tenantGroups(ChargeBatch $batch): Collection
    {
        return $batch->items
            ->groupBy('tenant_id')
            ->map(function (Collection $items): array {
                $tenant = $items->first()->tenant;
                $subtotal = $items
                    ->filter(fn (ChargeBatchItem $item) => $item->amount !== null && $item->item_status !== ChargeBatchItemStatus::Excluded)
                    ->sum(fn (ChargeBatchItem $item) => (float) $item->amount);

                return [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'unit_label' => $tenant->unit?->house_number,
                    'tenant_status' => $this->tenantResolutionStatus($items),
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'items' => $items->values(),
                ];
            })
            ->values();
    }

    private function createItemsForTenant(ChargeBatch $batch, Tenant $tenant, int $month, int $year): void
    {
        $rentAmount = (string) ($tenant->unit?->monthly_rent ?? 0);
        $serviceAmount = (string) ($tenant->service_amount ?? 0);

        ChargeBatchItem::query()->create([
            'charge_batch_id' => $batch->id,
            'tenant_id' => $tenant->id,
            'charge_type' => ChargeBatchItemType::Rent,
            'amount' => $rentAmount,
            'source_amount' => $rentAmount,
            'item_status' => ChargeBatchItemStatus::Draft,
        ]);

        ChargeBatchItem::query()->create([
            'charge_batch_id' => $batch->id,
            'tenant_id' => $tenant->id,
            'charge_type' => ChargeBatchItemType::Service,
            'amount' => $serviceAmount,
            'source_amount' => $serviceAmount,
            'item_status' => ChargeBatchItemStatus::Draft,
        ]);

        if ($tenant->requires_water_metering) {
            $this->createUtilityItem(
                $batch,
                $tenant,
                ChargeBatchItemType::Water,
                TenantWaterBill::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('billing_month', $month)
                    ->where('billing_year', $year)
                    ->first(),
                'tenant_water_bill_id',
                'missing_water_reading',
            );
        }

        if ($tenant->requires_electricity_metering) {
            $this->createUtilityItem(
                $batch,
                $tenant,
                ChargeBatchItemType::Electricity,
                TenantElectricityBill::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('billing_month', $month)
                    ->where('billing_year', $year)
                    ->first(),
                'tenant_electricity_bill_id',
                'missing_electricity_reading',
            );
        }
    }

    private function createUtilityItem(
        ChargeBatch $batch,
        Tenant $tenant,
        ChargeBatchItemType $type,
        ?object $bill,
        string $billForeignKey,
        string $pendingReason,
    ): void {
        if ($bill === null) {
            ChargeBatchItem::query()->create([
                'charge_batch_id' => $batch->id,
                'tenant_id' => $tenant->id,
                'charge_type' => $type,
                'item_status' => ChargeBatchItemStatus::Pending,
                'pending_reason' => $pendingReason,
            ]);

            return;
        }

        ChargeBatchItem::query()->create([
            'charge_batch_id' => $batch->id,
            'tenant_id' => $tenant->id,
            'charge_type' => $type,
            'amount' => $bill->amount,
            'source_amount' => $bill->amount,
            'item_status' => ChargeBatchItemStatus::Draft,
            $billForeignKey => $bill->id,
        ]);
    }

    private function refreshWaterItem(ChargeBatchItem $item, int $month, int $year): void
    {
        $bill = TenantWaterBill::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->first();

        if ($bill === null) {
            return;
        }

        $item->update([
            'amount' => $bill->amount,
            'source_amount' => $bill->amount,
            'item_status' => ChargeBatchItemStatus::Draft,
            'pending_reason' => null,
            'tenant_water_bill_id' => $bill->id,
        ]);
    }

    private function refreshElectricityItem(ChargeBatchItem $item, int $month, int $year): void
    {
        $bill = TenantElectricityBill::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->first();

        if ($bill === null) {
            return;
        }

        $item->update([
            'amount' => $bill->amount,
            'source_amount' => $bill->amount,
            'item_status' => ChargeBatchItemStatus::Draft,
            'pending_reason' => null,
            'tenant_electricity_bill_id' => $bill->id,
        ]);
    }

    private function syncBatchStatus(ChargeBatch $batch, User $user): void
    {
        $batch->refresh();
        $tenantIds = $batch->items->pluck('tenant_id')->unique();

        $allResolved = $tenantIds->every(fn (int $tenantId) => $this->isTenantResolved($batch->id, $tenantId));

        if ($allResolved) {
            $batch->update([
                'status' => ChargeBatchStatus::Locked,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);

            return;
        }

        $hasApproved = ChargeBatchItem::query()
            ->where('charge_batch_id', $batch->id)
            ->where('item_status', ChargeBatchItemStatus::Approved)
            ->exists();

        $batch->update([
            'status' => $hasApproved ? ChargeBatchStatus::PartiallyApproved : ChargeBatchStatus::Draft,
        ]);
    }

  private function isTenantResolved(int $batchId, int $tenantId): bool
    {
        if ($this->tenantIsExcluded($batchId, $tenantId)) {
            return true;
        }

        return ! ChargeBatchItem::query()
            ->where('charge_batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->whereIn('item_status', [ChargeBatchItemStatus::Draft, ChargeBatchItemStatus::Pending])
            ->exists();
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

    private function tenantHasPendingItems(int $batchId, int $tenantId): bool
    {
        return ChargeBatchItem::query()
            ->where('charge_batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->where('item_status', ChargeBatchItemStatus::Pending)
            ->exists();
    }

    /**
     * @return list<int>
     */
    private function unresolvedTenantIds(ChargeBatch $batch): array
    {
        return $batch->items
            ->pluck('tenant_id')
            ->unique()
            ->filter(fn (int $tenantId) => ! $this->isTenantResolved($batch->id, $tenantId))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ChargeBatchItem>  $items
     */
    private function tenantResolutionStatus(Collection $items): string
    {
        if ($items->every(fn (ChargeBatchItem $item) => $item->item_status === ChargeBatchItemStatus::Excluded)) {
            return 'excluded';
        }

        if ($items->contains(fn (ChargeBatchItem $item) => $item->item_status === ChargeBatchItemStatus::Pending)) {
            return 'pending';
        }

        if ($items->every(fn (ChargeBatchItem $item) => in_array($item->item_status, [ChargeBatchItemStatus::Approved, ChargeBatchItemStatus::Excluded], true))) {
            return 'approved';
        }

        if ($items->contains(fn (ChargeBatchItem $item) => $item->item_status === ChargeBatchItemStatus::Approved)) {
            return 'partial';
        }

        return 'draft';
    }

    private function assertEditable(ChargeBatch $batch): void
    {
        if (! $batch->isEditable()) {
            throw ValidationException::withMessages([
                'batch' => ['This charge batch is locked and cannot be modified.'],
            ]);
        }
    }
}
