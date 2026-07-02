<?php

namespace Tests\Feature\Rental;

use App\Enums\ChargeBatchItemStatus;
use App\Enums\ChargeBatchStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\ChargeBatch;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeBatchTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->rental()->manager()->create();
    }

    private function staff(): User
    {
        return User::factory()->rental()->create(['is_manager' => false]);
    }

    /**
     * @return array{building: RentalBuilding, tenant: Tenant, user: User}
     */
    private function seedActiveTenant(): array
    {
        $user = $this->manager();
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

        return compact('building', 'tenant', 'user');
    }

    public function test_staff_can_generate_draft_batch_without_posting_charges(): void
    {
        $data = $this->seedActiveTenant();
        $staff = $this->staff();

        $this->actingAs($staff)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ChargeBatchStatus::Draft->value);

        $this->assertDatabaseCount('rent_charges', 0);
        $this->assertDatabaseCount('charge_batch_items', 2);
    }

    public function test_duplicate_batch_generation_is_rejected(): void
    {
        $data = $this->seedActiveTenant();

        $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ])->assertOk();

        $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_month']);
    }

    public function test_manager_approval_posts_rent_charges_without_locking_batch(): void
    {
        $data = $this->seedActiveTenant();

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]);

        $batchId = $response->json('data.id');

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")
            ->assertOk()
            ->assertJsonPath('approved_tenants', 1);

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $data['tenant']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
            'purpose' => 'Rent + service',
            'rent_amount' => 65000,
            'service_amount' => 10000,
            'total_amount' => 75000,
        ]);

        $this->assertDatabaseHas('charge_batches', [
            'id' => $batchId,
            'status' => ChargeBatchStatus::PartiallyApproved->value,
            'locked_by' => null,
            'locked_at' => null,
        ]);
    }

    public function test_rental_staff_can_approve_batch(): void
    {
        $data = $this->seedActiveTenant();
        $staff = $this->staff();

        $response = $this->actingAs($staff)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]);

        $batchId = $response->json('data.id');

        $this->actingAs($staff)->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")
            ->assertOk()
            ->assertJsonPath('approved_tenants', 1);

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $data['tenant']->id,
            'purpose' => 'Rent + service',
        ]);
    }

    public function test_pending_water_item_blocks_lock_until_resolved_or_excluded(): void
    {
        $data = $this->seedActiveTenant();
        $data['tenant']->update(['requires_water_metering' => true]);

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]);

        $batchId = $response->json('data.id');

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")
            ->assertOk()
            ->assertJsonPath('approved_tenants', 1);

        $batch = ChargeBatch::query()->findOrFail($batchId);
        $this->assertSame(ChargeBatchStatus::PartiallyApproved, $batch->status);

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $data['tenant']->id,
            'purpose' => 'Rent + service',
        ]);

        $this->assertDatabaseMissing('rent_charges', [
            'tenant_id' => $data['tenant']->id,
            'purpose' => 'Water',
        ]);

        $this->assertDatabaseHas('charge_batch_items', [
            'charge_batch_id' => $batchId,
            'charge_type' => 'water',
            'item_status' => ChargeBatchItemStatus::Pending->value,
        ]);
    }

    public function test_pending_count_endpoint_returns_open_batches(): void
    {
        $data = $this->seedActiveTenant();

        $this->actingAs($data['user'])->getJson('/api/v1/rental/charge-batches/pending-count')
            ->assertOk()
            ->assertJsonPath('count', 0);

        $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]);

        $this->actingAs($data['user'])->getJson('/api/v1/rental/charge-batches/pending-count')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_manager_can_reopen_approved_tenant_for_editing(): void
    {
        $data = $this->seedActiveTenant();

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 6,
            'billing_year' => 2026,
        ]);

        $batchId = $response->json('data.id');
        $tenantId = $data['tenant']->id;

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/tenants/{$tenantId}/approve")
            ->assertOk();

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/tenants/{$tenantId}/reopen")
            ->assertOk()
            ->assertJsonPath('data.is_locked', false);

        $this->assertDatabaseHas('charge_batch_items', [
            'charge_batch_id' => $batchId,
            'tenant_id' => $tenantId,
            'charge_type' => 'rent',
            'item_status' => ChargeBatchItemStatus::Draft->value,
        ]);
    }

    public function test_water_bill_recorded_after_batch_syncs_pending_item_without_manual_refresh(): void
    {
        $data = $this->seedActiveTenant();
        $data['tenant']->update(['requires_water_metering' => true]);

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $batchId = $response->json('data.id');

        $this->assertDatabaseHas('charge_batch_items', [
            'charge_batch_id' => $batchId,
            'charge_type' => 'water',
            'item_status' => ChargeBatchItemStatus::Pending->value,
        ]);

        $this->actingAs($data['user'])->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $data['tenant']->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->assertDatabaseHas('charge_batch_items', [
            'charge_batch_id' => $batchId,
            'charge_type' => 'water',
            'item_status' => ChargeBatchItemStatus::Draft->value,
            'amount' => 2700,
        ]);

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")
            ->assertOk()
            ->assertJsonPath('approved_tenants', 1);

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $data['tenant']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'purpose' => 'Rent + service',
            'total_amount' => 75000,
        ]);

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $data['tenant']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'purpose' => 'Water',
            'total_amount' => 2700,
        ]);
    }

    public function test_water_bill_recorded_before_batch_approval_posts_to_balance_without_refresh(): void
    {
        $data = $this->seedActiveTenant();
        $data['tenant']->update(['requires_water_metering' => true]);

        $this->actingAs($data['user'])->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $data['tenant']->id,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $batchId = $response->json('data.id');

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")
            ->assertOk();

        $this->assertDatabaseCount('rent_charges', 2);
        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $data['tenant']->id,
            'purpose' => 'Water',
            'total_amount' => 2700,
        ]);
    }

    public function test_partial_approval_posts_water_when_bill_added_later(): void
    {
        $data = $this->seedActiveTenant();
        $data['tenant']->update(['requires_water_metering' => true]);

        $response = $this->actingAs($data['user'])->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $data['building']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
        ]);

        $batchId = $response->json('data.id');
        $tenantId = $data['tenant']->id;

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/tenants/{$tenantId}/approve")
            ->assertOk();

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $tenantId,
            'purpose' => 'Rent + service',
        ]);

        $this->assertDatabaseMissing('rent_charges', [
            'tenant_id' => $tenantId,
            'purpose' => 'Water',
        ]);

        $this->actingAs($data['user'])->postJson('/api/v1/rental/water-bills', [
            'tenant_id' => $tenantId,
            'rental_building_id' => $data['building']->id,
            'billing_month' => 7,
            'billing_year' => 2026,
            'previous_reading' => 100,
            'current_reading' => 150,
            'rate' => 50,
            'fixed_fee' => 200,
        ])->assertCreated();

        $this->actingAs($data['user'])->postJson("/api/v1/rental/charge-batches/{$batchId}/tenants/{$tenantId}/approve")
            ->assertOk();

        $this->assertDatabaseHas('rent_charges', [
            'tenant_id' => $tenantId,
            'purpose' => 'Water',
            'total_amount' => 2700,
        ]);
    }
}
