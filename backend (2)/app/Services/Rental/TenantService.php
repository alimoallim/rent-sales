<?php

namespace App\Services\Rental;

use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\TenantMoveOut;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $actor): Tenant
    {
        return DB::transaction(function () use ($data, $actor): Tenant {
            $unit = RentalUnit::query()->lockForUpdate()->findOrFail($data['rental_unit_id']);

            if ($unit->status !== RentalUnitStatus::Vacant) {
                throw ValidationException::withMessages([
                    'rental_unit_id' => ['This unit is not vacant.'],
                ]);
            }

            if ((int) $unit->rental_building_id !== (int) $data['rental_building_id']) {
                throw ValidationException::withMessages([
                    'rental_unit_id' => ['Unit does not belong to the selected building.'],
                ]);
            }

            $tenant = Tenant::query()->create([
                ...$data,
                'status' => TenantStatus::Active,
                'created_by' => $actor->id,
            ]);

            $unit->update(['status' => RentalUnitStatus::Occupied]);

            return $tenant->load(['building', 'unit']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        if ($tenant->status !== TenantStatus::Active) {
            throw ValidationException::withMessages([
                'tenant' => ['Only active tenants can be updated.'],
            ]);
        }

        return DB::transaction(function () use ($tenant, $data): Tenant {
            $newUnitId = (int) $data['rental_unit_id'];
            $oldUnitId = (int) $tenant->rental_unit_id;

            if ($newUnitId !== $oldUnitId) {
                $newUnit = RentalUnit::query()->lockForUpdate()->findOrFail($newUnitId);

                if ((int) $newUnit->rental_building_id !== (int) $data['rental_building_id']) {
                    throw ValidationException::withMessages([
                        'rental_unit_id' => ['Unit does not belong to the selected building.'],
                    ]);
                }

                if ($newUnit->status !== RentalUnitStatus::Vacant) {
                    throw ValidationException::withMessages([
                        'rental_unit_id' => ['This unit is not vacant.'],
                    ]);
                }

                RentalUnit::query()->whereKey($oldUnitId)->update([
                    'status' => RentalUnitStatus::Vacant,
                ]);

                $newUnit->update(['status' => RentalUnitStatus::Occupied]);
            }

            $tenant->update($data);

            return $tenant->fresh(['building', 'unit']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function moveOut(Tenant $tenant, array $data, User $actor): TenantMoveOut
    {
        if ($tenant->status !== TenantStatus::Active) {
            throw ValidationException::withMessages([
                'tenant' => ['This tenant has already moved out.'],
            ]);
        }

        return DB::transaction(function () use ($tenant, $data, $actor): TenantMoveOut {
            $moveOut = TenantMoveOut::query()->create([
                'tenant_id' => $tenant->id,
                'rental_building_id' => $tenant->rental_building_id,
                'rental_unit_id' => $tenant->rental_unit_id,
                'refund_amount' => $data['refund_amount'] ?? 0,
                'reason' => $data['reason'],
                'moved_out_at' => $data['moved_out_at'],
                'recorded_by' => $actor->id,
            ]);

            $tenant->update(['status' => TenantStatus::Inactive]);

            RentalUnit::query()->whereKey($tenant->rental_unit_id)->update([
                'status' => RentalUnitStatus::Vacant,
            ]);

            return $moveOut->load(['tenant', 'building', 'unit']);
        });
    }
}
