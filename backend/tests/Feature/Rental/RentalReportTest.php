<?php

namespace Tests\Feature\Rental;

use App\Enums\RentPaymentStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\BuildingElectricityBill;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\TenantWaterBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalReportTest extends TestCase
{
    use RefreshDatabase;

    private function rentalUser(): User
    {
        return User::factory()->rental()->create();
    }

    private function seedTenantWithFinancials(User $user): array
    {
        $building = RentalBuilding::query()->create(['name' => 'Baraka Towers']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '2 bed',
            'monthly_rent' => 65000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Jane Doe',
            'phone' => '0700000000',
            'deposit' => 0,
            'service_amount' => 10000,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $unit->id,
            'rental_building_id' => $building->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        RentPayment::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'amount' => 50000,
            'discount' => 5000,
            'paid_at' => '2026-06-15',
            'status' => RentPaymentStatus::Active,
            'created_by' => $user->id,
        ]);

        TenantWaterBill::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $building->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'consumption' => 50,
            'rate' => 50,
            'fixed_fee' => 200,
            'amount' => 2700,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        BuildingElectricityBill::query()->create([
            'rental_building_id' => $building->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'amount' => 12000,
            'billed_at' => '2026-06-10',
        ]);

        return compact('building', 'tenant');
    }

    public function test_tenant_balances_report_returns_outstanding_rows(): void
    {
        $user = $this->rentalUser();
        $data = $this->seedTenantWithFinancials($user);

        $this->actingAs($user)->getJson('/api/v1/rental/reports/tenant-balances?outstanding_only=1')
            ->assertOk()
            ->assertJsonPath('currency_code', 'KES')
            ->assertJsonPath('rows.0.tenant_name', 'Jane Doe')
            ->assertJsonPath('rows.0.balance', '20000.00')
            ->assertJsonPath('totals.balance', '20000.00');
    }

    public function test_payment_history_report_filters_by_date_range(): void
    {
        $user = $this->rentalUser();
        $this->seedTenantWithFinancials($user);

        $this->actingAs($user)->getJson('/api/v1/rental/reports/payment-history?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('totals.amount', '50000.00');

        $this->actingAs($user)->getJson('/api/v1/rental/reports/payment-history?from=2026-07-01')
            ->assertOk()
            ->assertJsonCount(0, 'rows');
    }

    public function test_charge_summary_report_totals_charges(): void
    {
        $user = $this->rentalUser();
        $this->seedTenantWithFinancials($user);

        $this->actingAs($user)->getJson('/api/v1/rental/reports/charge-summary?billing_month=6&billing_year=2026')
            ->assertOk()
            ->assertJsonPath('totals.total_amount', '75000.00');
    }

    public function test_income_statement_report_calculates_net_balance(): void
    {
        $user = $this->rentalUser();
        $data = $this->seedTenantWithFinancials($user);

        $this->actingAs($user)->getJson('/api/v1/rental/reports/income-statement?'.http_build_query([
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]))
            ->assertOk()
            ->assertJsonPath('calculation_mode', 'unified')
            ->assertJsonPath('lines.rent_collections', '50000.00')
            ->assertJsonPath('lines.service_income', '10000.00')
            ->assertJsonPath('lines.rent_net', '40000.00')
            ->assertJsonPath('lines.water_income', '0.00')
            ->assertJsonPath('lines.electricity', '12000.00')
            ->assertJsonPath('lines.net_balance_in_hand', '38000.00');
    }

    public function test_legacy_income_statement_counts_service_per_payment_and_water_bills(): void
    {
        $user = $this->rentalUser();
        $data = $this->seedTenantWithFinancials($user);

        RentPayment::query()->create([
            'tenant_id' => $data['tenant']->id,
            'rental_building_id' => $data['building']->id,
            'amount' => 25000,
            'discount' => 0,
            'paid_at' => '2026-06-20',
            'status' => RentPaymentStatus::Active,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson('/api/v1/rental/reports/income-statement?'.http_build_query([
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'mode' => 'legacy',
        ]))
            ->assertOk()
            ->assertJsonPath('calculation_mode', 'legacy')
            ->assertJsonPath('lines.rent_collections', '75000.00')
            ->assertJsonPath('lines.service_income', '20000.00')
            ->assertJsonPath('lines.water_income', '2700.00')
            ->assertJsonPath('lines.service_water_subtotal', '22700.00')
            ->assertJsonPath('lines.rent_net', '55000.00')
            ->assertJsonPath('lines.net_balance_in_hand', '65700.00');
    }

    public function test_tenant_balances_csv_export(): void
    {
        $user = $this->rentalUser();
        $this->seedTenantWithFinancials($user);

        $response = $this->actingAs($user)->get('/api/v1/rental/reports/tenant-balances?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Jane Doe', $response->streamedContent());
    }

    public function test_arrears_aging_report(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-07-06');

        $user = $this->rentalUser();
        $building = RentalBuilding::query()->create(['name' => 'Aging Tower']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'B1',
            'floor' => '1',
            'description' => '1 bed',
            'monthly_rent' => 50000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Overdue Tenant',
            'phone' => '0700111222',
            'deposit' => 0,
            'service_amount' => 0,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $unit->id,
            'rental_building_id' => $building->id,
            'billing_month' => 1,
            'billing_year' => 2026,
            'rent_amount' => 50000,
            'service_amount' => 0,
            'total_amount' => 50000,
            'purpose' => 'Rent + service',
            'charged_at' => '2026-01-31',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/rental/reports/arrears-aging?outstanding_only=1')
            ->assertOk()
            ->assertJsonPath('totals.tenants', 1)
            ->assertJsonPath('rows.0.tenant_name', 'Overdue Tenant')
            ->assertJsonPath('rows.0.days_90_plus', '50000.00');

        $this->actingAs($user)
            ->get('/api/v1/rental/reports/arrears-aging?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
