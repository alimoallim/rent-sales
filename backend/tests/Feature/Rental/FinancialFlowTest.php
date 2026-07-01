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
use App\Models\User;
use App\Services\Rental\TenantBalanceBreakdownService;
use App\Services\Rental\TenantBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialFlowTest extends TestCase
{
    use RefreshDatabase;

    private function rentalUser(): User
    {
        return User::factory()->rental()->manager()->create();
    }

    private function generateAndApproveBatch(User $user, int $buildingId, int $month, int $year): int
    {
        $response = $this->actingAs($user)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $buildingId,
            'billing_month' => $month,
            'billing_year' => $year,
        ])->assertOk();

        $batchId = (int) $response->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/rental/charge-batches/{$batchId}/refresh-pending");
        $this->actingAs($user)->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")->assertOk();

        return $batchId;
    }

    private function postWaterChargeForTenant(Tenant $tenant, int $month, int $year, int $billId, string $amount): void
    {
        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => $month,
            'billing_year' => $year,
            'rent_amount' => 0,
            'service_amount' => 0,
            'total_amount' => $amount,
            'purpose' => 'Water',
            'tenant_water_bill_id' => $billId,
            'charged_at' => now(),
        ]);
    }

    private function postElectricityChargeForTenant(Tenant $tenant, int $month, int $year, int $billId, string $amount): void
    {
        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => $month,
            'billing_year' => $year,
            'rent_amount' => 0,
            'service_amount' => 0,
            'total_amount' => $amount,
            'purpose' => 'Electricity',
            'tenant_electricity_bill_id' => $billId,
            'charged_at' => now(),
        ]);
    }

    private function activeTenant(User $user): Tenant
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

        return Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Jane Doe',
            'phone' => '0700000000',
            'deposit' => 0,
            'service_amount' => 10000,
            'status' => TenantStatus::Active,
            'created_by' => $user->id,
        ]);
    }

    public function test_balance_is_charges_minus_active_payments(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
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
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 50000,
            'discount' => 5000,
            'paid_at' => now(),
            'status' => RentPaymentStatus::Active,
            'created_by' => $user->id,
        ]);

        $balance = app(TenantBalanceCalculator::class)->calculate($tenant);

        $this->assertSame('20000.00', $balance);
    }

    public function test_voided_payments_do_not_reduce_balance(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $payment = RentPayment::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 50000,
            'discount' => 0,
            'paid_at' => now(),
            'status' => RentPaymentStatus::Active,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson("/api/v1/rental/payments/{$payment->id}/void")
            ->assertOk()
            ->assertJsonPath('data.status', RentPaymentStatus::Voided->value);

        $balance = app(TenantBalanceCalculator::class)->calculate($tenant);

        $this->assertSame('75000.00', $balance);
    }

    public function test_charge_batch_generation_is_idempotent_per_building_and_period(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        $first = $this->actingAs($user)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]);

        $first->assertOk();

        $this->actingAs($user)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ])->assertUnprocessable();

        $batchId = (int) $first->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")->assertOk();

        $this->assertDatabaseCount('rent_charges', 1);
        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $tenant->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
        ]);
    }

    public function test_tenant_index_includes_balance_for_active_tenants(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/rental/tenants?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.balance', '75000.00');
    }

    public function test_water_bill_calculates_consumption_and_amount(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated()
            ->assertJsonPath('data.consumption', 50)
            ->assertJsonPath('data.amount', '2700.00')
            ->assertJsonPath('data.status', WaterBillStatus::Pending->value);

        $this->assertDatabaseMissing('rent_charges', [
            'tenant_id' => $tenant->id,
            'purpose' => 'Water',
        ]);
    }

    public function test_water_bill_posts_to_balance_after_batch_approval(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_water_metering' => true]);

        $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->generateAndApproveBatch($user, $tenant->rental_building_id, 6, 2026);

        $balance = app(TenantBalanceCalculator::class)->calculate($tenant);

        $this->assertSame('77700.00', $balance);
    }

    public function test_rent_and_water_charges_can_coexist_for_same_period(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_water_metering' => true]);

        $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->generateAndApproveBatch($user, $tenant->rental_building_id, 6, 2026);

        $this->assertDatabaseCount('rent_charges', 2);

        $balance = app(TenantBalanceCalculator::class)->calculate($tenant);

        $this->assertSame('77700.00', $balance);
    }

    public function test_water_bill_duplicate_period_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        $payload = [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ];

        $this->actingAs($user)->postJson('/api/v1/rental/water-bills', $payload)->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/rental/water-bills', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_month']);
    }

    public function test_payment_summary_shows_category_breakdown(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $waterBill = $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->postWaterChargeForTenant(
            $tenant,
            6,
            2026,
            (int) $waterBill->json('data.id'),
            '2700.00',
        );

        $this->actingAs($user)->getJson("/api/v1/rental/tenants/{$tenant->id}/payment-summary")
            ->assertOk()
            ->assertJsonPath('data.water_owed', '2700.00')
            ->assertJsonPath('data.electricity_owed', '0.00')
            ->assertJsonPath('data.services_owed', '10000.00')
            ->assertJsonPath('data.rent_owed', '65000.00')
            ->assertJsonPath('data.total_due', '77700.00')
            ->assertJsonPath('data.status', 'owes');
    }

    public function test_partial_payment_applies_water_first_then_services_then_rent(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $waterBill = $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->postWaterChargeForTenant(
            $tenant,
            6,
            2026,
            (int) $waterBill->json('data.id'),
            '2700.00',
        );

        RentPayment::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 15000,
            'discount' => 0,
            'paid_at' => now(),
            'status' => RentPaymentStatus::Active,
            'created_by' => $user->id,
        ]);

        $breakdown = app(TenantBalanceBreakdownService::class)->breakdown($tenant);

        $this->assertSame('0.00', $breakdown['water_owed']);
        $this->assertSame('0.00', $breakdown['services_owed']);
        $this->assertSame('62700.00', $breakdown['rent_owed']);
        $this->assertSame('62700.00', $breakdown['total_due']);
    }

    public function test_overpayment_requires_acknowledgement(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 80000,
            'discount' => 0,
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['overpayment_acknowledged']);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 80000,
            'discount' => 0,
            'paid_at' => now()->toDateString(),
            'overpayment_acknowledged' => true,
        ])->assertCreated();

        $breakdown = app(TenantBalanceBreakdownService::class)->breakdown($tenant);

        $this->assertSame('credit', $breakdown['status']);
        $this->assertSame('5000.00', $breakdown['credit_balance']);
    }

    public function test_payment_summary_includes_water_meter_reminder_when_required_and_missing(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_water_metering' => true]);

        $this->actingAs($user)->getJson("/api/v1/rental/tenants/{$tenant->id}/payment-summary")
            ->assertOk()
            ->assertJsonCount(1, 'data.meter_reading_reminders')
            ->assertJsonPath('data.payment_blocked', true)
            ->assertJsonPath('data.meter_reading_reminders.0.utility', 'water')
            ->assertJsonPath('data.meter_reading_reminders.0.tenant_name', 'Jane Doe');
    }

    public function test_payment_blocked_when_water_reading_missing(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_water_metering' => true]);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 10000,
            'discount' => 0,
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['meter_reading.water']);
    }

    public function test_payment_allowed_after_water_reading_recorded(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_water_metering' => true]);

        $now = now();

        $waterBill = $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => (int) $now->month,
            'billing_year' => (int) $now->year,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->postWaterChargeForTenant(
            $tenant,
            (int) $now->month,
            (int) $now->year,
            (int) $waterBill->json('data.id'),
            '2700.00',
        );

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 2700,
            'discount' => 0,
            'paid_at' => $now->toDateString(),
        ])->assertCreated();
    }

    public function test_payment_summary_omits_reminder_when_water_reading_exists(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_water_metering' => true]);

        $now = now();

        $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => (int) $now->month,
            'billing_year' => (int) $now->year,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->actingAs($user)->getJson("/api/v1/rental/tenants/{$tenant->id}/payment-summary")
            ->assertOk()
            ->assertJsonCount(0, 'data.meter_reading_reminders');
    }

    public function test_payment_summary_omits_reminder_when_water_metering_not_required(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        $this->actingAs($user)->getJson("/api/v1/rental/tenants/{$tenant->id}/payment-summary")
            ->assertOk()
            ->assertJsonCount(0, 'data.meter_reading_reminders');
    }

    public function test_payment_summary_includes_contract_metering_flags(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update([
            'requires_water_metering' => true,
            'requires_electricity_metering' => false,
        ]);

        $this->actingAs($user)->getJson("/api/v1/rental/tenants/{$tenant->id}/payment-summary")
            ->assertOk()
            ->assertJsonPath('data.contract.requires_water_metering', true)
            ->assertJsonPath('data.contract.requires_electricity_metering', false);
    }

    public function test_electricity_bill_posts_to_balance_after_batch_approval(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_electricity_metering' => true]);

        $this->actingAs($user)->postJson('/api/v1/rental/electricity-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 1000,
            'current_reading' => 1200,
            'rate' => 20,
            'fixed_fee' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.consumption', 200)
            ->assertJsonPath('data.amount', '4100.00');

        $this->generateAndApproveBatch($user, $tenant->rental_building_id, 6, 2026);

        $balance = app(TenantBalanceCalculator::class)->calculate($tenant);

        $this->assertSame('79100.00', $balance);

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $tenant->id,
            'purpose' => 'Electricity',
            'total_amount' => 4100,
        ]);
    }

    public function test_payment_blocked_when_electricity_reading_missing(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update(['requires_electricity_metering' => true]);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 1000,
            'discount' => 0,
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['meter_reading.electricity']);
    }

    public function test_partial_payment_applies_water_then_electricity_then_services_then_rent(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        RentCharge::query()->create([
            'tenant_id' => $tenant->id,
            'rental_unit_id' => $tenant->rental_unit_id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
            'purpose' => 'Rent + service',
            'charged_at' => now(),
        ]);

        $waterBill = $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $electricityBill = $this->actingAs($user)->postJson('/api/v1/rental/electricity-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 1000,
            'current_reading' => 1100,
            'rate' => 10,
            'fixed_fee' => 0,
        ])->assertCreated();

        $this->postWaterChargeForTenant($tenant, 6, 2026, (int) $waterBill->json('data.id'), '2700.00');
        $this->postElectricityChargeForTenant($tenant, 6, 2026, (int) $electricityBill->json('data.id'), '1000.00');

        RentPayment::query()->create([
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 5000,
            'discount' => 0,
            'paid_at' => now(),
            'status' => RentPaymentStatus::Active,
            'created_by' => $user->id,
        ]);

        $breakdown = app(TenantBalanceBreakdownService::class)->breakdown($tenant);

        $this->assertSame('0.00', $breakdown['water_owed']);
        $this->assertSame('0.00', $breakdown['electricity_owed']);
        $this->assertSame('8700.00', $breakdown['services_owed']);
        $this->assertSame('65000.00', $breakdown['rent_owed']);
    }

    public function test_payment_requires_tenant_building_and_positive_amount(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'amount' => 0,
            'discount' => 0,
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_id', 'rental_building_id']);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'amount' => 0,
            'discount' => 0,
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_tenant_charges_index_includes_rent_water_and_electricity(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenant($user);
        $tenant->update([
            'requires_water_metering' => true,
            'requires_electricity_metering' => true,
        ]);

        $this->actingAs($user)->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/rental/electricity-bills', [
            'tenant_id' => $tenant->id,
            'rental_building_id' => $tenant->rental_building_id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'previous_reading' => 1000,
            'current_reading' => 1200,
            'rate' => 20,
            'fixed_fee' => 100,
        ])->assertCreated();

        $this->generateAndApproveBatch($user, $tenant->rental_building_id, 6, 2026);

        $response = $this->actingAs($user)->getJson("/api/v1/rental/charges?tenant_id={$tenant->id}")
            ->assertOk();

        $purposes = collect($response->json('data'))->pluck('purpose')->sort()->values()->all();

        $this->assertSame(['Electricity', 'Rent + service', 'Water'], $purposes);
    }
}
