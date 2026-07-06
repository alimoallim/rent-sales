<?php

namespace Tests\Feature\Rental;

use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFlowTest extends TestCase
{
    use RefreshDatabase;

    private function rentalUser(): User
    {
        return User::factory()->rental()->create();
    }

    public function test_tenant_registration_marks_unit_occupied(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '2 bed',
            'monthly_rent' => 65000,
            'status' => RentalUnitStatus::Vacant,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/rental/tenants', [
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Jane Doe',
            'phone' => '0700000000',
            'deposit' => 50000,
            'service_amount' => 10000,
            'start_date' => '2026-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.status', TenantStatus::Active->value);

        $this->assertDatabaseHas('rental_units', [
            'id' => $unit->id,
            'status' => RentalUnitStatus::Occupied->value,
        ]);
    }

    public function test_tenant_registration_stores_contract_metering_flags(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'C3',
            'floor' => '3',
            'description' => '2 bed',
            'monthly_rent' => 60000,
            'status' => RentalUnitStatus::Vacant,
        ]);

        $this->actingAs($user)->postJson('/api/v1/rental/tenants', [
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Metered Tenant',
            'phone' => '0722222222',
            'deposit' => 40000,
            'service_amount' => 8000,
            'start_date' => '2026-06-01',
            'requires_water_metering' => true,
            'requires_electricity_metering' => true,
        ])->assertCreated()
            ->assertJsonPath('data.requires_water_metering', true)
            ->assertJsonPath('data.requires_electricity_metering', true)
            ->assertJsonPath('data.contract.requires_water_metering', true)
            ->assertJsonPath('data.contract.requires_electricity_metering', true);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Metered Tenant',
            'requires_water_metering' => true,
            'requires_electricity_metering' => true,
        ]);
    }

    public function test_cannot_register_tenant_on_occupied_unit(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '2 bed',
            'monthly_rent' => 65000,
            'status' => RentalUnitStatus::Occupied,
        ]);

        $this->actingAs($user)->postJson('/api/v1/rental/tenants', [
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Jane Doe',
            'phone' => '0700000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['rental_unit_id']);
    }

    public function test_move_out_marks_tenant_inactive_and_unit_vacant(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'B2',
            'floor' => '2',
            'description' => '3 bed',
            'monthly_rent' => 70000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'John Doe',
            'phone' => '0711111111',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson("/api/v1/rental/tenants/{$tenant->id}/move-out", [
            'refund_amount' => 50000,
            'reason' => 'Relocated',
            'moved_out_at' => '2026-06-01',
        ])->assertCreated()
            ->assertJsonPath('data.reason', 'Relocated');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => TenantStatus::Inactive->value,
        ]);
        $this->assertDatabaseHas('rental_units', [
            'id' => $unit->id,
            'status' => RentalUnitStatus::Vacant->value,
        ]);
        $this->assertDatabaseHas('tenant_move_outs', [
            'tenant_id' => $tenant->id,
            'refund_amount' => 50000,
        ]);
    }

    public function test_inactive_tenant_list_includes_balance_and_accepts_arrears_payment(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Arrears Block']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'C3',
            'floor' => '3',
            'description' => '2 bed',
            'monthly_rent' => 50000,
            'status' => RentalUnitStatus::Vacant,
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Former Tenant',
            'phone' => '0722222222',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Inactive,
            'requires_water_metering' => true,
            'created_by' => $user->id,
        ]);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'billing_month' => 5,
            'billing_year' => 2026,
            'rent_amount' => 50000,
            'service_amount' => 0,
            'total_amount' => 50000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/rental/tenants?status=inactive')
            ->assertOk()
            ->assertJsonPath('data.0.balance', '50000.00')
            ->assertJsonPath('summary.with_balance', 1);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'amount' => '20000.00',
            'discount' => '0.00',
            'paid_at' => '2026-06-10',
        ])->assertCreated();
    }

    public function test_building_with_units_cannot_be_deleted(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '2 bed',
            'monthly_rent' => 65000,
            'status' => RentalUnitStatus::Vacant,
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/rental/buildings/{$building->id}")
            ->assertUnprocessable();
    }

    public function test_units_index_includes_summary_and_active_tenant(): void
    {
        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $occupiedUnit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '2 bed',
            'monthly_rent' => 65000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'B2',
            'floor' => '2',
            'description' => '1 bed',
            'monthly_rent' => 45000,
            'status' => RentalUnitStatus::Vacant,
        ]);
        Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $occupiedUnit->id,
            'name' => 'Jane Doe',
            'phone' => '0700000000',
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson('/api/v1/rental/units')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.vacant', 1)
            ->assertJsonPath('summary.occupied', 1)
            ->assertJsonPath('summary.occupancy_rate', 50)
            ->assertJsonPath('data.0.active_tenant.name', 'Jane Doe');
    }
}
