<?php

namespace Tests\Feature\Sales;

use App\Enums\SaleUnitStatus;
use App\Enums\UserRole;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesCurrencyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->where('role', UserRole::Sales)->firstOrFail();
    }

    #[Test]
    public function test_sales_write_endpoints_reject_client_supplied_currency_code(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'Test Tower']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Unit',
            'list_price' => '100000.00',
            'status' => SaleUnitStatus::Available,
        ]);

        $this->actingAs($this->user)->postJson('/api/v1/sales/clients', [
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Buyer',
            'phone' => '0700000000',
            'agreed_sale_price' => '100000.00',
            'currency_code' => 'KES',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_code']);

        $clientResponse = $this->actingAs($this->user)->postJson('/api/v1/sales/clients', [
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Buyer',
            'phone' => '0700000000',
            'agreed_sale_price' => '100000.00',
        ])->assertCreated();

        $clientId = $clientResponse->json('data.id');

        $this->actingAs($this->user)->postJson('/api/v1/sales/payments', [
            'client_id' => $clientId,
            'sale_building_id' => $building->id,
            'amount' => '10000.00',
            'paid_at' => '2025-04-01',
            'currency_code' => 'EUR',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_code']);
    }
}
