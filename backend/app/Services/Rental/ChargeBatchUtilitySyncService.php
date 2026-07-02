<?php

namespace App\Services\Rental;

use App\Enums\ChargeBatchItemStatus;
use App\Enums\ChargeBatchItemType;
use App\Enums\TenantStatus;
use App\Models\ChargeBatch;
use App\Models\ChargeBatchItem;
use App\Models\Tenant;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use Illuminate\Support\Carbon;

class ChargeBatchUtilitySyncService
{
    public function __construct(
        private readonly ChargeBatchPostingService $postingService,
    ) {}

  public function syncTenantsForBatch(ChargeBatch $batch): void
    {
        if (! $batch->isEditable()) {
            return;
        }

        $periodEnd = Carbon::create($batch->billing_year, $batch->billing_month, 1)->endOfMonth();

        $tenants = Tenant::query()
            ->with('unit')
            ->where('rental_building_id', $batch->rental_building_id)
            ->where('status', TenantStatus::Active)
            ->where(function ($query) use ($periodEnd): void {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $periodEnd);
            })
            ->get();

        foreach ($tenants as $tenant) {
            $this->ensureTenantItems($batch, $tenant);
        }
    }

    public function syncWaterBill(TenantWaterBill $bill): void
    {
        $batch = $this->findEditableBatch($bill->tenant_id, $bill->billing_month, $bill->billing_year);

        if ($batch === null) {
            return;
        }

        $item = $this->findUtilityItem($batch, $bill->tenant_id, ChargeBatchItemType::Water);

        if ($item === null) {
            $tenant = Tenant::query()->with('unit')->find($bill->tenant_id);
            if ($tenant?->requires_water_metering) {
                $this->ensureRentServiceItems($batch, $tenant);
                $this->createDraftUtilityItem($batch, $bill->tenant_id, ChargeBatchItemType::Water, $bill, 'tenant_water_bill_id');
            }

            return;
        }

        $this->applyBillToItem($batch, $item, $bill->amount, 'tenant_water_bill_id', $bill->id);
    }

    public function syncElectricityBill(TenantElectricityBill $bill): void
    {
        $batch = $this->findEditableBatch($bill->tenant_id, $bill->billing_month, $bill->billing_year);

        if ($batch === null) {
            return;
        }

        $item = $this->findUtilityItem($batch, $bill->tenant_id, ChargeBatchItemType::Electricity);

        if ($item === null) {
            $tenant = Tenant::query()->with('unit')->find($bill->tenant_id);
            if ($tenant?->requires_electricity_metering) {
                $this->ensureRentServiceItems($batch, $tenant);
                $this->createDraftUtilityItem($batch, $bill->tenant_id, ChargeBatchItemType::Electricity, $bill, 'tenant_electricity_bill_id');
            }

            return;
        }

        $this->applyBillToItem($batch, $item, $bill->amount, 'tenant_electricity_bill_id', $bill->id);
    }

    public function refreshPendingItems(ChargeBatch $batch): void
    {
        $batch->loadMissing('items');

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
    }

    private function findEditableBatch(int $tenantId, int $month, int $year): ?ChargeBatch
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return null;
        }

        $batch = ChargeBatch::query()
            ->where('rental_building_id', $tenant->rental_building_id)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->first();

        if ($batch === null || ! $batch->isEditable()) {
            return null;
        }

        return $batch;
    }

    private function findUtilityItem(ChargeBatch $batch, int $tenantId, ChargeBatchItemType $type): ?ChargeBatchItem
    {
        return ChargeBatchItem::query()
            ->where('charge_batch_id', $batch->id)
            ->where('tenant_id', $tenantId)
            ->where('charge_type', $type)
            ->first();
    }

    private function createDraftUtilityItem(
        ChargeBatch $batch,
        int $tenantId,
        ChargeBatchItemType $type,
        TenantWaterBill|TenantElectricityBill $bill,
        string $billForeignKey,
    ): void {
        ChargeBatchItem::query()->create([
            'charge_batch_id' => $batch->id,
            'tenant_id' => $tenantId,
            'charge_type' => $type,
            'amount' => $bill->amount,
            'source_amount' => $bill->amount,
            'item_status' => ChargeBatchItemStatus::Draft,
            $billForeignKey => $bill->id,
        ]);
    }

    private function applyBillToItem(
        ChargeBatch $batch,
        ChargeBatchItem $item,
        string $amount,
        string $billForeignKey,
        int $billId,
    ): void {
        if ($item->item_status === ChargeBatchItemStatus::Excluded) {
            return;
        }

        if ($item->item_status === ChargeBatchItemStatus::Pending) {
            $item->update([
                'amount' => $amount,
                'source_amount' => $amount,
                'item_status' => ChargeBatchItemStatus::Draft,
                'pending_reason' => null,
                $billForeignKey => $billId,
            ]);

            return;
        }

        if ($item->manually_adjusted) {
            return;
        }

        $item->update([
            'amount' => $amount,
            'source_amount' => $amount,
            $billForeignKey => $billId,
        ]);

        if ($item->item_status === ChargeBatchItemStatus::Approved) {
            $this->postingService->syncTenantPostedCharges($batch, $item->tenant_id);
        }
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

    private function ensureTenantItems(ChargeBatch $batch, Tenant $tenant): void
    {
        $this->ensureRentServiceItems($batch, $tenant);

        if ($tenant->requires_water_metering) {
            $this->ensureUtilityItem(
                $batch,
                $tenant,
                ChargeBatchItemType::Water,
                TenantWaterBill::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('billing_month', $batch->billing_month)
                    ->where('billing_year', $batch->billing_year)
                    ->first(),
                'tenant_water_bill_id',
                'missing_water_reading',
            );
        }

        if ($tenant->requires_electricity_metering) {
            $this->ensureUtilityItem(
                $batch,
                $tenant,
                ChargeBatchItemType::Electricity,
                TenantElectricityBill::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('billing_month', $batch->billing_month)
                    ->where('billing_year', $batch->billing_year)
                    ->first(),
                'tenant_electricity_bill_id',
                'missing_electricity_reading',
            );
        }
    }

    private function ensureRentServiceItems(ChargeBatch $batch, Tenant $tenant): void
    {
        $rentAmount = (string) ($tenant->unit?->monthly_rent ?? 0);
        $serviceAmount = (string) ($tenant->service_amount ?? 0);

        $this->ensureRentServiceItem($batch, $tenant->id, ChargeBatchItemType::Rent, $rentAmount);
        $this->ensureRentServiceItem($batch, $tenant->id, ChargeBatchItemType::Service, $serviceAmount);
    }

    private function ensureRentServiceItem(
        ChargeBatch $batch,
        int $tenantId,
        ChargeBatchItemType $type,
        string $amount,
    ): void {
        $item = ChargeBatchItem::query()
            ->where('charge_batch_id', $batch->id)
            ->where('tenant_id', $tenantId)
            ->where('charge_type', $type)
            ->first();

        if ($item === null) {
            ChargeBatchItem::query()->create([
                'charge_batch_id' => $batch->id,
                'tenant_id' => $tenantId,
                'charge_type' => $type,
                'amount' => $amount,
                'source_amount' => $amount,
                'item_status' => ChargeBatchItemStatus::Draft,
            ]);

            return;
        }

        if (in_array($item->item_status, [ChargeBatchItemStatus::Excluded, ChargeBatchItemStatus::Approved], true)) {
            return;
        }

        if ($item->manually_adjusted) {
            return;
        }

        if (bccomp((string) ($item->amount ?? 0), $amount, 2) === 0) {
            return;
        }

        $item->update([
            'amount' => $amount,
            'source_amount' => $amount,
            'item_status' => $item->item_status === ChargeBatchItemStatus::Pending
                ? ChargeBatchItemStatus::Draft
                : $item->item_status,
            'pending_reason' => null,
        ]);
    }

    private function ensureUtilityItem(
        ChargeBatch $batch,
        Tenant $tenant,
        ChargeBatchItemType $type,
        ?object $bill,
        string $billForeignKey,
        string $pendingReason,
    ): void {
        $item = ChargeBatchItem::query()
            ->where('charge_batch_id', $batch->id)
            ->where('tenant_id', $tenant->id)
            ->where('charge_type', $type)
            ->first();

        if ($item !== null) {
            return;
        }

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
}
