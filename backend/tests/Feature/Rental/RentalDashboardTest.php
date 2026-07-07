<?php

namespace Tests\Feature\Rental;

use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Enums\WaterBillStatus;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\TenantWaterBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_operational_insights(): void
    {
        $user = User::factory()->rental()->create();
        $building = RentalBuilding::query()->create(['name' => 'Block A']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '2 bed',
            'monthly_rent' => 10000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Jane Tenant',
            'phone' => '0700000000',
            'status' => TenantStatus::Active,
            'service_amount' => 500,
        ]);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $unit->id,
            'rental_building_id' => $building->id,
            'billing_month' => now()->month,
            'billing_year' => now()->year,
            'rent_amount' => 10000,
            'service_amount' => 500,
            'total_amount' => 10500,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        RentPayment::createActive([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'amount' => 2000,
            'discount' => 0,
            'paid_at' => now(),
            'invoice_reference' => 'INV-001',
        ], $user->id);

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'billing_month' => now()->month,
            'billing_year' => now()->year,
            'previous_reading' => 100,
            'current_reading' => 110,
            'consumption' => 10,
            'rate' => 30,
            'fixed_fee' => 0,
            'amount' => 300,
            'status' => WaterBillStatus::Recorded,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/rental/dashboard');

        $response->assertOk()
            ->assertJsonPath('currency_code', 'KES')
            ->assertJsonPath('occupancy.buildings', 1)
            ->assertJsonPath('occupancy.active_tenants', 1)
            ->assertJsonPath('occupancy.occupied_units', 1)
            ->assertJsonPath('collections.payment_count_current_month', 1)
            ->assertJsonPath('utilities.missing_water_readings.count', 0)
            ->assertJsonPath('outstanding.tenants_with_balance', 1)
            ->assertJsonStructure([
                'period',
                'occupancy',
                'collections',
                'outstanding',
                'utilities',
                'operations',
                'charges',
                'top_debtors',
                'recent_payments',
                'recent_move_outs',
                'building_summary',
                'action_required',
            ]);

        $this->assertGreaterThan(0, (float) $response->json('outstanding.total_balance'));
        $this->assertSame('Jane Tenant', $response->json('top_debtors.0.tenant_name'));
        $this->assertSame('Block A', $response->json('building_summary.0.building_name'));
    }

    public function test_dashboard_outstanding_aligns_with_tenant_index(): void
    {
        $user = User::factory()->rental()->create();
        $building = RentalBuilding::query()->create(['name' => 'Alignment Block']);
        $unitA = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Unit A',
            'monthly_rent' => 10000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        $unitB = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'B2',
            'floor' => '2',
            'description' => 'Unit B',
            'monthly_rent' => 12000,
            'status' => RentalUnitStatus::Occupied,
        ]);

        $tenantA = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unitA->id,
            'name' => 'Owing Tenant',
            'phone' => '0700000001',
            'status' => TenantStatus::Active,
            'service_amount' => 500,
        ]);
        $tenantB = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unitB->id,
            'name' => 'Paid Tenant',
            'phone' => '0700000002',
            'status' => TenantStatus::Active,
            'service_amount' => 500,
        ]);

        RentCharge::query()->create([
            'tenant_id' => $tenantA->id,
            'rental_unit_id' => $unitA->id,
            'rental_building_id' => $building->id,
            'billing_month' => now()->month,
            'billing_year' => now()->year,
            'rent_amount' => 10000,
            'service_amount' => 500,
            'total_amount' => 10500,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);
        RentCharge::query()->create([
            'tenant_id' => $tenantB->id,
            'rental_unit_id' => $unitB->id,
            'rental_building_id' => $building->id,
            'billing_month' => now()->month,
            'billing_year' => now()->year,
            'rent_amount' => 8000,
            'service_amount' => 500,
            'total_amount' => 8500,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        RentPayment::createActive([
            'tenant_id' => $tenantB->id,
            'rental_building_id' => $building->id,
            'amount' => 8500,
            'discount' => 0,
            'paid_at' => now(),
        ], $user->id);

        $dashboard = $this->actingAs($user)->getJson('/api/v1/rental/dashboard')->assertOk();
        $index = $this->actingAs($user)->getJson('/api/v1/rental/tenants?status=active')->assertOk();

        $this->assertSame($index->json('summary.total_outstanding'), $dashboard->json('outstanding.total_balance'));
        $this->assertSame($index->json('summary.with_balance'), $dashboard->json('outstanding.tenants_with_balance'));
        $this->assertSame('10500.00', $dashboard->json('outstanding.total_balance'));
        $this->assertSame('Owing Tenant', $dashboard->json('top_debtors.0.tenant_name'));
        $this->assertSame('10500.00', $dashboard->json('top_debtors.0.balance'));
        $this->assertSame('10500.00', $dashboard->json('building_summary.0.outstanding_balance'));
    }
}
