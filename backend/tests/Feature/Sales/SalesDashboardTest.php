<?php

namespace Tests\Feature\Sales;

use App\Enums\ClientStatus;
use App\Enums\SalesPaymentStatus;
use App\Enums\SaleUnitStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\SaleBuilding;
use App\Models\SalesPayment;
use App\Models\SaleUnit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_sales_operational_insights(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('role', UserRole::Sales)->firstOrFail();

        $building = SaleBuilding::query()->create(['name' => 'Tower One']);
        $soldUnit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => '3 bed',
            'list_price' => '120000.00',
            'status' => SaleUnitStatus::Sold,
        ]);
        SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'B2',
            'floor' => '2',
            'description' => '2 bed',
            'list_price' => '90000.00',
            'status' => SaleUnitStatus::Available,
        ]);

        $client = Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $soldUnit->id,
            'name' => 'Jane Buyer',
            'phone' => '0700000000',
            'agreed_sale_price' => '100000.00',
            'deposit' => '10000.00',
            'registration_date' => now()->toDateString(),
            'status' => ClientStatus::Active,
        ]);

        SalesPayment::createActive([
            'client_id' => $client->id,
            'sale_building_id' => $building->id,
            'amount' => '20000.00',
            'discount' => '0.00',
            'paid_at' => now(),
        ], $user->id);

        $response = $this->actingAs($user)->getJson('/api/v1/sales/dashboard');

        $response->assertOk()
            ->assertJsonPath('currency_code', 'USD')
            ->assertJsonPath('inventory.buildings', 1)
            ->assertJsonPath('inventory.available_units', 1)
            ->assertJsonPath('inventory.sold_units', 1)
            ->assertJsonPath('portfolio.active_clients', 1)
            ->assertJsonPath('portfolio.clients_with_balance', 1)
            ->assertJsonPath('collections.payment_count_current_month', 1)
            ->assertJsonPath('operations.new_clients_this_month', 1)
            ->assertJsonStructure([
                'generated_at',
                'currency_code',
                'period',
                'inventory',
                'portfolio',
                'collections',
                'operations',
                'pipeline',
                'top_outstanding',
                'recent_payments',
                'recent_registrations',
                'available_inventory',
                'building_summary',
            ]);

        $this->assertSame('Jane Buyer', $response->json('top_outstanding.0.client_name'));
        $this->assertSame('B2', $response->json('available_inventory.0.house_number'));
        $this->assertSame('Tower One', $response->json('building_summary.0.building_name'));
        $this->assertGreaterThan(0, (float) $response->json('portfolio.outstanding_total'));
    }
}
