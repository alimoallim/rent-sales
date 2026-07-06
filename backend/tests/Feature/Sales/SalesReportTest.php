<?php

namespace Tests\Feature\Sales;

use App\Enums\ClientStatus;
use App\Enums\SalesPaymentStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\SalesPayment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesReportTest extends TestCase
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
    public function test_balance_report_csv_export(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'Export Tower']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'E1',
            'floor' => '1',
            'description' => '2 bedroom',
            'list_price' => '120000.00',
        ]);

        Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'CSV Buyer',
            'phone' => '0700111222',
            'agreed_sale_price' => '100000.00',
            'deposit' => '5000.00',
            'registration_date' => '2025-01-15',
        ]);

        $response = $this->actingAs($this->user)->get('/api/v1/sales/reports/balance?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV Buyer', $response->streamedContent());
        $this->assertStringContainsString('Sale price', $response->streamedContent());
    }

    #[Test]
    public function test_income_statement_csv_export(): void
    {
        $response = $this->actingAs($this->user)->get('/api/v1/sales/reports/income-statement?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Income total', $response->streamedContent());
        $this->assertStringContainsString('Payments', $response->streamedContent());
    }

    #[Test]
    public function test_cancelled_clients_report(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'Archive Tower']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'A1',
            'floor' => '1',
            'description' => 'Studio',
            'list_price' => '80000.00',
        ]);

        Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Disabled Buyer',
            'phone' => '0700333444',
            'agreed_sale_price' => '75000.00',
            'deposit' => '2000.00',
            'registration_date' => '2024-06-01',
            'status' => ClientStatus::Disabled,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/sales/reports/cancelled-clients');

        $response->assertOk()
            ->assertJsonPath('totals.clients', 1)
            ->assertJsonPath('rows.0.client_name', 'Disabled Buyer');
    }

    #[Test]
    public function test_cancelled_payments_report(): void
    {
        $building = SaleBuilding::query()->create(['name' => 'Void Tower']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'V1',
            'floor' => '2',
            'description' => '1 bedroom',
            'list_price' => '90000.00',
        ]);

        $client = Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Refund Client',
            'phone' => '0700555666',
            'agreed_sale_price' => '85000.00',
            'deposit' => '1000.00',
            'registration_date' => '2024-08-01',
        ]);

        SalesPayment::query()->create([
            'client_id' => $client->id,
            'sale_building_id' => $building->id,
            'amount' => '5000.00',
            'discount' => '0.00',
            'paid_at' => '2024-09-01',
            'status' => SalesPaymentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/sales/reports/cancelled-payments');

        $response->assertOk()
            ->assertJsonPath('totals.payments', 1)
            ->assertJsonPath('rows.0.client_name', 'Refund Client')
            ->assertJsonPath('rows.0.cancelled_by_name', $this->user->name);
    }
}
