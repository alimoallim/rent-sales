<?php

namespace Tests\Feature\Rental;

use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Exceptions\DuplicateRentChargeException;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rental\RentChargePostingGuard;
use App\Services\Rental\TenantBalanceCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChargeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->rental()->manager()->create();
    }

    private function activeTenant(User $user): Tenant
    {
        $building = RentalBuilding::query()->create(['name' => 'Integrity Tower']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'B1',
            'floor' => '1',
            'description' => 'Studio',
            'monthly_rent' => 50000,
            'status' => RentalUnitStatus::Occupied,
        ]);

        return Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'John Integrity',
            'phone' => '0700111222',
            'deposit' => 0,
            'service_amount' => 5000,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);
    }

    public function test_database_rejects_duplicate_rent_charge_for_same_period(): void
    {
        $user = $this->manager();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 50000,
            'service_amount' => 5000,
            'total_amount' => 55000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 50000,
            'service_amount' => 5000,
            'total_amount' => 55000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);
    }

    public function test_database_allows_rent_water_and_electricity_for_same_period(): void
    {
        $user = $this->manager();
        $tenant = $this->activeTenant($user);

        foreach (['Rent + service', 'Water', 'Electricity'] as $purpose) {
            RentCharge::query()->create([
                'tenant_id' => $tenant->id,
                'rental_unit_id' => $tenant->rental_unit_id,
                'rental_building_id' => $tenant->rental_building_id,
                'billing_month' => 6,
                'billing_year' => 2026,
                'rent_amount' => 0,
                'service_amount' => 0,
                'total_amount' => 1000,
                'purpose' => $purpose,
                'charged_at' => now(),
            ]);
        }

        $this->assertDatabaseCount('rent_charges', 3);
    }

    public function test_approve_all_twice_does_not_double_tenant_balance(): void
    {
        $user = $this->manager();
        $tenant = $this->activeTenant($user);

        $response = $this->actingAs($user)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ])->assertOk();

        $batchId = (int) $response->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")->assertOk();

        $this->assertDatabaseCount('rent_charges', 1);

        $balance = app(TenantBalanceCalculator::class)->calculate($tenant);

        $this->assertSame('55000.00', $balance);
    }

    public function test_posting_guard_rejects_second_billable_charge_for_period(): void
    {
        $user = $this->manager();
        $tenant = $this->activeTenant($user);
        $guard = app(RentChargePostingGuard::class);

        $guard->createOrFail(
            $tenant,
            6,
            2026,
            RentChargePostingGuard::PURPOSE_RENT_SERVICE,
            [
                'rent_amount' => 50000,
                'service_amount' => 5000,
                'total_amount' => 55000,
                'charged_at' => now(),
            ],
        );

        $this->expectException(DuplicateRentChargeException::class);

        $guard->createOrFail(
            $tenant,
            6,
            2026,
            RentChargePostingGuard::PURPOSE_RENT_SERVICE,
            [
                'rent_amount' => 50000,
                'service_amount' => 5000,
                'total_amount' => 55000,
                'charged_at' => now(),
            ],
        );
    }

    public function test_financial_integrity_indexes_exist_in_database(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL partial unique indexes are required.');
        }

        $indexes = DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'rent_charges'
              AND indexname IN ('rent_charges_unique_billable_period', 'rent_charges_unique_batch_item')
        ");

        $this->assertCount(2, $indexes);
    }
}
