<?php

namespace Tests\Feature\Sales;

use App\Enums\ClientStatus;
use App\Enums\SaleUnitStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesFlowTest extends TestCase
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
    public function test_sales_user_can_register_client_and_record_payment(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'BARAKA TOWERS 2']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '3 bedroom',
            'list_price' => '150000.00',
            'status' => SaleUnitStatus::Available,
        ]);

        $clientResponse = $this->actingAs($this->user)->postJson('/api/v1/sales/clients', [
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Test Buyer',
            'phone' => '0700000000',
            'agreed_sale_price' => '100000.00',
            'deposit' => '10000.00',
            'registration_date' => '2025-03-26',
        ]);

        $clientResponse->assertCreated();
        $this->assertSame('sold', $unit->fresh()->status->value);

        $clientId = $clientResponse->json('data.id');

        $this->actingAs($this->user)->postJson('/api/v1/sales/payments', [
            'client_id' => $clientId,
            'sale_building_id' => $building->id,
            'amount' => '20000.00',
            'discount' => '0.00',
            'paid_at' => '2025-04-01',
        ])->assertCreated();

        $this->actingAs($this->user)->getJson("/api/v1/sales/clients/{$clientId}/payment-summary")
            ->assertOk()
            ->assertJsonPath('data.balance', '70000.00');
    }

    #[Test]
    public function test_cannot_disable_client_with_active_payments(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'Test Building']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'B2',
            'floor' => '2',
            'description' => 'Duplex',
            'list_price' => '60000.00',
            'status' => SaleUnitStatus::Sold,
        ]);

        $client = Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Buyer',
            'phone' => '0711111111',
            'agreed_sale_price' => '60000.00',
            'deposit' => '0.00',
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($this->user)->postJson('/api/v1/sales/payments', [
            'client_id' => $client->id,
            'sale_building_id' => $building->id,
            'amount' => '10000.00',
            'paid_at' => '2025-05-01',
        ])->assertCreated();

        $this->actingAs($this->user)->postJson("/api/v1/sales/clients/{$client->id}/disable")
            ->assertStatus(422);
    }

    #[Test]
    public function test_dashboard_returns_sales_metrics(): void
    {
        $this->actingAs($this->user)->getJson('/api/v1/sales/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'buildings',
                'active_clients',
                'available_units',
                'sold_units',
                'clients_with_balance',
            ]);
    }
}
