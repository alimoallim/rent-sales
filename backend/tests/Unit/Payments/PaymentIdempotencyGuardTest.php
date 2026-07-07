<?php

namespace Tests\Unit\Payments;

use App\Enums\RentPaymentStatus;
use App\Models\RentPayment;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentIdempotencyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentIdempotencyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_recent_duplicate_matches_active_payment_in_window(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        $user = User::factory()->rental()->create();
        $building = RentalBuilding::query()->create(['name' => 'Guard Tower']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'G1',
            'floor' => '1',
            'description' => 'Unit',
            'monthly_rent' => 1000,
            'status' => 'occupied',
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Tenant',
            'phone' => '0700000000',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $payment = RentPayment::createActive([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'amount' => '1500.00',
            'discount' => '0.00',
            'paid_at' => '2026-07-01',
        ], $user->id);

        $guard = new PaymentIdempotencyGuard;
        $duplicate = $guard->findRecentDuplicate(
            RentPayment::query()
                ->where('tenant_id', $tenant->id)
                ->where('rental_building_id', $building->id)
                ->where('status', RentPaymentStatus::Active),
            '1500.00',
            '0.00',
            '2026-07-01',
            $user->id,
            null,
        );

        $this->assertNotNull($duplicate);
        $this->assertSame($payment->id, $duplicate->id);

        Carbon::setTestNow('2026-07-01 12:02:00');

        $outsideWindow = $guard->findRecentDuplicate(
            RentPayment::query()
                ->where('tenant_id', $tenant->id)
                ->where('rental_building_id', $building->id)
                ->where('status', RentPaymentStatus::Active),
            '1500.00',
            '0.00',
            '2026-07-01',
            $user->id,
            null,
        );

        $this->assertNull($outsideWindow);

        Carbon::setTestNow();
    }
}
