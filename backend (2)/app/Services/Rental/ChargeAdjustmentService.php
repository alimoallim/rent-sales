<?php

namespace App\Services\Rental;

use App\Enums\ChargeAdjustmentType;
use App\Models\ChargeAdjustment;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChargeAdjustmentService
{
    public function __construct(private readonly RentChargePostingGuard $postingGuard) {}

    public function createCredit(
        Tenant $tenant,
        int $month,
        int $year,
        string $amount,
        string $reason,
        User $manager,
    ): ChargeAdjustment {
        if (! $manager->isManager()) {
            abort(403, 'Only managers can post charge adjustments.');
        }

        if (bccomp($amount, '0', 2) === 0) {
            throw ValidationException::withMessages([
                'amount' => ['Adjustment amount cannot be zero.'],
            ]);
        }

        return DB::transaction(function () use ($tenant, $month, $year, $amount, $reason, $manager): ChargeAdjustment {
            $charge = $this->postingGuard->createOrFail(
                $tenant,
                $month,
                $year,
                RentChargePostingGuard::PURPOSE_ADJUSTMENT,
                [
                    'rent_amount' => 0,
                    'service_amount' => 0,
                    'total_amount' => $amount,
                    'charged_at' => now(),
                ],
            );

            return ChargeAdjustment::query()->create([
                'tenant_id' => $tenant->id,
                'rental_building_id' => $tenant->rental_building_id,
                'billing_month' => $month,
                'billing_year' => $year,
                'charge_type' => ChargeAdjustmentType::Credit,
                'amount' => $amount,
                'reason' => $reason,
                'rent_charge_id' => $charge->id,
                'created_by' => $manager->id,
                'approved_by' => $manager->id,
                'approved_at' => now(),
            ]);
        });
    }
}
