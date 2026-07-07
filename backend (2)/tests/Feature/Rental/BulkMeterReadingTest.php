<?php

namespace Tests\Feature\Rental;

use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\TenantWaterBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkMeterReadingTest extends TestCase
{
    use RefreshDatabase;

    private function rentalUser(): User
    {
        return User::factory()->rental()->manager()->create();
    }

    /**
     * @return array{user: User, building: RentalBuilding, tenants: list<Tenant>}
     */
    private function meteredTenants(int $count = 2): array
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);

        $tenants = [];

        for ($i = 1; $i <= $count; $i++) {
            $unit = RentalUnit::query()->create([
                'rental_building_id' => $building->id,
                'house_number' => "A{$i}",
                'floor' => '1',
                'description' => '2 bed',
                'monthly_rent' => 65000,
                'status' => RentalUnitStatus::Occupied,
            ]);

            $tenants[] = Tenant::query()->create([
                'rental_building_id' => $building->id,
                'rental_unit_id' => $unit->id,
                'name' => "Tenant {$i}",
                'phone' => '0700000000',
                'deposit' => 0,
                'service_amount' => 10000,
                'requires_water_metering' => true,
                'requires_electricity_metering' => true,
                'status' => TenantStatus::Active,
                'created_by' => $user->id,
            ]);
        }

        return ['user' => $user, 'building' => $building, 'tenants' => $tenants];
    }

    public function test_grid_returns_previous_readings_from_latest_bill(): void
    {
        $data = $this->meteredTenants();
        $tenant = $data['tenants'][0];

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 4,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 145,
            'consumption' => 45,
            'rate' => 55,
            'fixed_fee' => 0,
            'amount' => 2475,
            'status' => 'recorded',
            'created_by' => $data['user']->id,
        ]);

        $response = $this->actingAs($data['user'])->getJson('/api/v1/rental/bulk-meter-readings?'.http_build_query([
            'utility' => 'water',
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.rows.0.tenant_id', $tenant->id)
            ->assertJsonPath('data.rows.0.previous_reading', 145)
            ->assertJsonPath('data.rows.0.default_rate', '55.00');
    }

    public function test_bulk_store_saves_partial_readings_and_skips_blanks(): void
    {
        $data = $this->meteredTenants();
        [$tenantA, $tenantB] = $data['tenants'];

        TenantWaterBill::query()->create([
            'tenant_id' => $tenantA->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'previous_reading' => 0,
            'current_reading' => 100,
            'consumption' => 100,
            'rate' => 50,
            'fixed_fee' => 0,
            'amount' => 5000,
            'status' => 'recorded',
            'created_by' => $data['user']->id,
        ]);

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/bulk-meter-readings', [
            'utility' => 'water',
            'rental_building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'readings' => [
                ['tenant_id' => $tenantA->id, 'current_reading' => 130],
                ['tenant_id' => $tenantB->id, 'current_reading' => null],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.saved_count', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.error_count', 0);

        $this->assertDatabaseHas('tenant_water_bills', [
            'tenant_id' => $tenantA->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 130,
            'consumption' => 30,
        ]);

        $this->assertDatabaseMissing('tenant_water_bills', [
            'tenant_id' => $tenantB->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]);
    }

    public function test_bulk_store_rejects_reading_below_previous(): void
    {
        $data = $this->meteredTenants(1);
        $tenant = $data['tenants'][0];

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'previous_reading' => 0,
            'current_reading' => 200,
            'consumption' => 200,
            'rate' => 50,
            'fixed_fee' => 0,
            'amount' => 10000,
            'status' => 'recorded',
            'created_by' => $data['user']->id,
        ]);

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/bulk-meter-readings', [
            'utility' => 'water',
            'rental_building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'readings' => [
                ['tenant_id' => $tenant->id, 'current_reading' => 150],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.saved_count', 0)
            ->assertJsonPath('data.error_count', 1)
            ->assertJsonPath('data.results.0.status', 'error');
    }

    public function test_grid_marks_already_recorded_period_rows(): void
    {
        $data = $this->meteredTenants(1);
        $tenant = $data['tenants'][0];

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 140,
            'consumption' => 40,
            'rate' => 50,
            'fixed_fee' => 0,
            'amount' => 2000,
            'status' => 'recorded',
            'created_by' => $data['user']->id,
        ]);

        $this->actingAs($data['user'])->getJson('/api/v1/rental/bulk-meter-readings?'.http_build_query([
            'utility' => 'water',
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]))
            ->assertOk()
            ->assertJsonPath('data.rows.0.already_recorded', true)
            ->assertJsonPath('data.rows.0.existing_current_reading', 140);
    }

    public function test_grid_marks_first_reading_rows_without_prior_bill(): void
    {
        $data = $this->meteredTenants(1);
        $tenant = $data['tenants'][0];

        $this->actingAs($data['user'])->getJson('/api/v1/rental/bulk-meter-readings?'.http_build_query([
            'utility' => 'water',
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]))
            ->assertOk()
            ->assertJsonPath('data.rows.0.tenant_id', $tenant->id)
            ->assertJsonPath('data.rows.0.is_first_reading', true)
            ->assertJsonPath('data.rows.0.previous_reading_locked', false)
            ->assertJsonPath('data.rows.0.previous_reading', 0);
    }

    public function test_grid_locks_previous_reading_after_first_bill(): void
    {
        $data = $this->meteredTenants(1);
        $tenant = $data['tenants'][0];

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'previous_reading' => 80,
            'current_reading' => 120,
            'consumption' => 40,
            'rate' => 50,
            'fixed_fee' => 0,
            'amount' => 2000,
            'status' => 'recorded',
            'created_by' => $data['user']->id,
        ]);

        $this->actingAs($data['user'])->getJson('/api/v1/rental/bulk-meter-readings?'.http_build_query([
            'utility' => 'water',
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]))
            ->assertOk()
            ->assertJsonPath('data.rows.0.is_first_reading', false)
            ->assertJsonPath('data.rows.0.previous_reading_locked', true)
            ->assertJsonPath('data.rows.0.previous_reading', 120);
    }

    public function test_bulk_first_reading_accepts_custom_opening_reading(): void
    {
        $data = $this->meteredTenants(1);
        $tenant = $data['tenants'][0];

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/bulk-meter-readings', [
            'utility' => 'water',
            'rental_building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'readings' => [
                ['tenant_id' => $tenant->id, 'previous_reading' => 25, 'current_reading' => 80],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.saved_count', 1);

        $this->assertDatabaseHas('tenant_water_bills', [
            'tenant_id' => $tenant->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 25,
            'current_reading' => 80,
            'consumption' => 55,
        ]);
    }

    public function test_bulk_store_rejects_modified_previous_when_locked(): void
    {
        $data = $this->meteredTenants(1);
        $tenant = $data['tenants'][0];

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'previous_reading' => 0,
            'current_reading' => 100,
            'consumption' => 100,
            'rate' => 50,
            'fixed_fee' => 0,
            'amount' => 5000,
            'status' => 'recorded',
            'created_by' => $data['user']->id,
        ]);

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/bulk-meter-readings', [
            'utility' => 'water',
            'rental_building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'readings' => [
                ['tenant_id' => $tenant->id, 'previous_reading' => 50, 'current_reading' => 130],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.saved_count', 0)
            ->assertJsonPath('data.error_count', 1)
            ->assertJsonPath('data.results.0.message', 'Previous reading is fixed from the last recorded meter reading and cannot be changed.');
    }

    public function test_individual_water_bill_rejects_modified_previous_when_locked(): void
    {
        $data = $this->meteredTenants(1);
        $tenant = $data['tenants'][0];

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'previous_reading' => 0,
            'current_reading' => 100,
            'consumption' => 100,
            'rate' => 50,
            'fixed_fee' => 0,
            'amount' => 5000,
            'status' => 'recorded',
            'created_by' => $data['user']->id,
        ]);

        $this->actingAs($data['user'])->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 50,
            'current_reading' => 130,
            'rate' => 50,
            'fixed_fee' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['previous_reading']);
    }
}
