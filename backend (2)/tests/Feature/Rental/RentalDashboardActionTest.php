<?php

namespace Tests\Feature\Rental;

use App\Enums\ChargeBatchItemStatus;
use App\Enums\ChargeBatchItemType;
use App\Enums\ChargeBatchStatus;
use App\Enums\RentalUnitStatus;
use App\Enums\TenantStatus;
use App\Models\ChargeBatch;
use App\Models\ChargeBatchItem;
use App\Models\RentalBuilding;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_action_required_for_missing_readings_and_batches(): void
    {
        $user = User::factory()->rental()->create();
        $building = RentalBuilding::query()->create(['name' => 'Tower B']);
        $unit = RentalUnit::query()->create([
            'rental_building_id' => $building->id,
            'house_number' => 'B2',
            'floor' => '2',
            'description' => '1 bed',
            'monthly_rent' => 8000,
            'status' => RentalUnitStatus::Occupied,
        ]);
        $tenant = Tenant::query()->create([
            'rental_building_id' => $building->id,
            'rental_unit_id' => $unit->id,
            'name' => 'Meter Tenant',
            'phone' => '0700111222',
            'status' => TenantStatus::Active,
            'requires_water_metering' => true,
            'service_amount' => 200,
        ]);

        $batch = ChargeBatch::query()->create([
            'rental_building_id' => $building->id,
            'billing_month' => now()->month,
            'billing_year' => now()->year,
            'status' => ChargeBatchStatus::Draft,
            'generated_by' => $user->id,
            'generated_at' => now(),
        ]);

        ChargeBatchItem::query()->create([
            'charge_batch_id' => $batch->id,
            'tenant_id' => $tenant->id,
            'charge_type' => ChargeBatchItemType::Water,
            'item_status' => ChargeBatchItemStatus::Pending,
            'pending_reason' => 'missing_water_reading',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/rental/dashboard');

        $response->assertOk()
            ->assertJsonPath('action_required.total_count', fn ($count) => $count >= 2)
            ->assertJsonPath('action_required.high_priority_count', fn ($count) => $count >= 1);

        $types = collect($response->json('action_required.items'))->pluck('type')->all();
        $this->assertContains('missing_meter_reading', $types);
        $this->assertContains('charge_batch_pending_readings', $types);
    }
}
