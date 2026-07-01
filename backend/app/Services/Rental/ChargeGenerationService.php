<?php

namespace App\Services\Rental;

use App\Enums\TenantStatus;
use App\Models\RentCharge;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChargeGenerationService
{
    public function __construct(private readonly RentChargePostingGuard $postingGuard) {}

    /**
     * @return Collection<int, RentCharge>
     */
    public function generateForPeriod(int $month, int $year): Collection
    {
        $created = collect();

        Tenant::query()
            ->with(['unit'])
            ->where('status', TenantStatus::Active)
            ->chunkById(100, function ($tenants) use ($month, $year, $created): void {
                foreach ($tenants as $tenant) {
                    $charge = $this->createChargeIfMissing($tenant, $month, $year);
                    if ($charge !== null) {
                        $created->push($charge);
                    }
                }
            });

        return $created;
    }

    public function generateForCurrentMonth(): Collection
    {
        $now = Carbon::now();

        return $this->generateForPeriod((int) $now->month, (int) $now->year);
    }

    private function createChargeIfMissing(Tenant $tenant, int $month, int $year): ?RentCharge
    {
        $exists = RentCharge::query()
            ->where('tenant_id', $tenant->id)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->where('purpose', 'Rent + service')
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($tenant, $month, $year): RentCharge {
            $rentAmount = $tenant->unit?->monthly_rent ?? 0;
            $serviceAmount = $tenant->service_amount ?? 0;
            $total = bcadd((string) $rentAmount, (string) $serviceAmount, 2);

            return $this->postingGuard->createOrFail(
                $tenant,
                $month,
                $year,
                RentChargePostingGuard::PURPOSE_RENT_SERVICE,
                [
                    'rent_amount' => $rentAmount,
                    'service_amount' => $serviceAmount,
                    'total_amount' => $total,
                    'charged_at' => now(),
                ],
            );
        });
    }
}
