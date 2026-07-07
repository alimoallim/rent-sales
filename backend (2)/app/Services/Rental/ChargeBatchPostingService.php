<?php

namespace App\Services\Rental;

use App\Enums\ChargeBatchItemStatus;
use App\Enums\ChargeBatchItemType;
use App\Models\ChargeBatch;
use App\Models\ChargeBatchItem;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChargeBatchPostingService
{
    public function __construct(private readonly RentChargePostingGuard $postingGuard) {}

    /**
     * Post all postable draft items for a tenant and mark them approved.
     *
     * @return list<RentCharge>
     */
    public function postTenantItems(ChargeBatch $batch, int $tenantId, User $approver): array
    {
        return DB::transaction(function () use ($batch, $tenantId, $approver): array {
            $items = ChargeBatchItem::query()
                ->where('charge_batch_id', $batch->id)
                ->where('tenant_id', $tenantId)
                ->where('item_status', ChargeBatchItemStatus::Draft)
                ->whereNotNull('amount')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return [];
            }

            $tenant = Tenant::query()->with('unit')->lockForUpdate()->findOrFail($tenantId);
            $posted = [];

            $rentItem = $items->firstWhere('charge_type', ChargeBatchItemType::Rent);
            $serviceItem = $items->firstWhere('charge_type', ChargeBatchItemType::Service);

            if ($rentItem !== null || $serviceItem !== null) {
                $posted[] = $this->postRentServiceCharge($batch, $tenant, $rentItem, $serviceItem, $approver);
            }

            foreach ($items as $item) {
                if ($item->charge_type === ChargeBatchItemType::Water) {
                    $posted[] = $this->postUtilityCharge(
                        $batch,
                        $tenant,
                        $item,
                        RentChargePostingGuard::PURPOSE_WATER,
                        'tenant_water_bill_id',
                        $approver,
                    );
                }

                if ($item->charge_type === ChargeBatchItemType::Electricity) {
                    $posted[] = $this->postUtilityCharge(
                        $batch,
                        $tenant,
                        $item,
                        RentChargePostingGuard::PURPOSE_ELECTRICITY,
                        'tenant_electricity_bill_id',
                        $approver,
                    );
                }
            }

            return $posted;
        });
    }

    public function syncTenantPostedCharges(ChargeBatch $batch, int $tenantId): void
    {
        DB::transaction(function () use ($batch, $tenantId): void {
            $items = ChargeBatchItem::query()
                ->where('charge_batch_id', $batch->id)
                ->where('tenant_id', $tenantId)
                ->where('item_status', ChargeBatchItemStatus::Approved)
                ->whereNotNull('amount')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return;
            }

            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenantId);

            $rentItem = $items->firstWhere('charge_type', ChargeBatchItemType::Rent);
            $serviceItem = $items->firstWhere('charge_type', ChargeBatchItemType::Service);

            if ($rentItem !== null || $serviceItem !== null) {
                $this->upsertRentServiceCharge($batch, $tenant, $rentItem, $serviceItem);
            }

            foreach ($items as $item) {
                if ($item->charge_type === ChargeBatchItemType::Water) {
                    $this->upsertUtilityCharge(
                        $batch,
                        $tenant,
                        $item,
                        RentChargePostingGuard::PURPOSE_WATER,
                        'tenant_water_bill_id',
                    );
                }

                if ($item->charge_type === ChargeBatchItemType::Electricity) {
                    $this->upsertUtilityCharge(
                        $batch,
                        $tenant,
                        $item,
                        RentChargePostingGuard::PURPOSE_ELECTRICITY,
                        'tenant_electricity_bill_id',
                    );
                }
            }
        });
    }

    private function postRentServiceCharge(
        ChargeBatch $batch,
        Tenant $tenant,
        ?ChargeBatchItem $rentItem,
        ?ChargeBatchItem $serviceItem,
        User $approver,
    ): RentCharge {
        $charge = $this->upsertRentServiceCharge($batch, $tenant, $rentItem, $serviceItem);

        $this->markApproved($rentItem, $approver);
        $this->markApproved($serviceItem, $approver);

        return $charge;
    }

    private function upsertRentServiceCharge(
        ChargeBatch $batch,
        Tenant $tenant,
        ?ChargeBatchItem $rentItem,
        ?ChargeBatchItem $serviceItem,
    ): RentCharge {
        $rentAmount = (string) ($rentItem?->amount ?? 0);
        $serviceAmount = (string) ($serviceItem?->amount ?? 0);
        $total = bcadd($rentAmount, $serviceAmount, 2);
        $purpose = RentChargePostingGuard::PURPOSE_RENT_SERVICE;

        $existing = RentCharge::query()
            ->where('tenant_id', $tenant->id)
            ->where('billing_month', $batch->billing_month)
            ->where('billing_year', $batch->billing_year)
            ->where('purpose', $purpose)
            ->lockForUpdate()
            ->first();

        $attributes = [
            'rent_amount' => $rentAmount,
            'service_amount' => $serviceAmount,
            'total_amount' => $total,
            'charge_batch_item_id' => $rentItem?->id,
            'charged_at' => now(),
        ];

        if ($existing !== null) {
            return $this->postingGuard->updatePosted($existing, $attributes);
        }

        return $this->postingGuard->createOrFail($tenant, $batch->billing_month, $batch->billing_year, $purpose, $attributes);
    }

    private function postUtilityCharge(
        ChargeBatch $batch,
        Tenant $tenant,
        ChargeBatchItem $item,
        string $purpose,
        string $billForeignKey,
        User $approver,
    ): RentCharge {
        $charge = $this->upsertUtilityCharge($batch, $tenant, $item, $purpose, $billForeignKey);

        $this->markApproved($item, $approver);

        return $charge;
    }

    private function upsertUtilityCharge(
        ChargeBatch $batch,
        Tenant $tenant,
        ChargeBatchItem $item,
        string $purpose,
        string $billForeignKey,
    ): RentCharge {
        $existing = RentCharge::query()
            ->where('tenant_id', $tenant->id)
            ->where('billing_month', $batch->billing_month)
            ->where('billing_year', $batch->billing_year)
            ->where('purpose', $purpose)
            ->lockForUpdate()
            ->first();

        $attributes = [
            'rent_amount' => 0,
            'service_amount' => 0,
            'total_amount' => $item->amount,
            'charge_batch_item_id' => $item->id,
            $billForeignKey => $item->{$billForeignKey},
            'charged_at' => now(),
        ];

        if ($existing !== null) {
            return $this->postingGuard->updatePosted($existing, $attributes);
        }

        return $this->postingGuard->createOrFail($tenant, $batch->billing_month, $batch->billing_year, $purpose, $attributes);
    }

    private function markApproved(?ChargeBatchItem $item, User $approver): void
    {
        if ($item === null) {
            return;
        }

        $item->update([
            'item_status' => ChargeBatchItemStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }
}
