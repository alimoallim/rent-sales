<?php

namespace Tests\Feature\Sales;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
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
}
