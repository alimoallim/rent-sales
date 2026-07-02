<?php

namespace App\Services\Rental;

use App\Exceptions\DuplicateRentChargeException;
use App\Models\RentCharge;
use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RentChargePostingGuard
{
    public const PURPOSE_RENT_SERVICE = 'Rent + service';

    public const PURPOSE_WATER = 'Water';

    public const PURPOSE_ELECTRICITY = 'Electricity';

    public const PURPOSE_ADJUSTMENT = 'Adjustment';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createOrFail(Tenant $tenant, int $month, int $year, string $purpose, array $attributes): RentCharge
    {
        return DB::transaction(function () use ($tenant, $month, $year, $purpose, $attributes): RentCharge {
            $existing = RentCharge::query()
                ->where('tenant_id', $tenant->id)
                ->where('billing_month', $month)
                ->where('billing_year', $year)
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->charge_batch_item_id !== null) {
                    throw DuplicateRentChargeException::forPeriod($tenant->id, $month, $year, $purpose);
                }

                if ($purpose !== self::PURPOSE_ADJUSTMENT) {
                    throw DuplicateRentChargeException::forPeriod($tenant->id, $month, $year, $purpose);
                }
            }

            if (isset($attributes['charge_batch_item_id'])) {
                $postedItem = RentCharge::query()
                    ->where('charge_batch_item_id', $attributes['charge_batch_item_id'])
                    ->lockForUpdate()
                    ->exists();

                if ($postedItem) {
                    throw DuplicateRentChargeException::fromBatchItem((int) $attributes['charge_batch_item_id']);
                }
            }

            try {
                return RentCharge::query()->create([
                    'tenant_id' => $tenant->id,
                    'rental_unit_id' => $tenant->rental_unit_id,
                    'rental_building_id' => $tenant->rental_building_id,
                    'billing_month' => $month,
                    'billing_year' => $year,
                    'purpose' => $purpose,
                    ...$attributes,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw DuplicateRentChargeException::forPeriod($tenant->id, $month, $year, $purpose);
            }
        });
    }

    /**
     * Link a legacy (imported) charge to a batch posting exactly once.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function linkLegacyOnce(RentCharge $existing, array $attributes): RentCharge
    {
        if ($existing->charge_batch_item_id !== null) {
            throw DuplicateRentChargeException::forPeriod(
                $existing->tenant_id,
                $existing->billing_month,
                $existing->billing_year,
                $existing->purpose,
            );
        }

        $existing->fill($attributes);
        $existing->save();

        return $existing->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePosted(RentCharge $existing, array $attributes): RentCharge
    {
        $existing->fill($attributes);
        $existing->save();

        return $existing->fresh();
    }
}
