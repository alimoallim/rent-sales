<?php

namespace Tests\Feature\CriticalPath;

use App\Enums\ClientStatus;
use App\Enums\SaleUnitStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\SaleBuilding;
use App\Models\SaleUnit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    public function test_rental_double_submit_returns_single_payment(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '10000.00');

        $payload = $this->paymentPayload($tenant, '2500.00');

        $first = $this->actingAs($user)->postJson('/api/v1/rental/payments', $payload)->assertCreated();
        $second = $this->actingAs($user)->postJson('/api/v1/rental/payments', $payload)->assertOk();

        $this->assertDatabaseCount('rent_payments', 1);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
    }

    public function test_rental_identical_payments_outside_dedup_window_create_two_records(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');

        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '10000.00');
        $payload = $this->paymentPayload($tenant, '2500.00');

        $this->actingAs($user)->postJson('/api/v1/rental/payments', $payload)->assertCreated();

        Carbon::setTestNow('2026-07-01 10:02:00');

        $this->actingAs($user)->postJson('/api/v1/rental/payments', $payload)->assertCreated();

        $this->assertDatabaseCount('rent_payments', 2);

        Carbon::setTestNow();
    }

    public function test_sales_double_submit_returns_single_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('role', UserRole::Sales)->firstOrFail();

        $building = SaleBuilding::query()->create(['name' => 'Idempotency Plaza']);
        $unit = SaleUnit::query()->create([
            'sale_building_id' => $building->id,
            'house_number' => 'I1',
            'floor' => '1',
            'description' => 'Unit',
            'list_price' => '100000.00',
            'status' => SaleUnitStatus::Available,
        ]);
        $client = Client::query()->create([
            'sale_building_id' => $building->id,
            'sale_unit_id' => $unit->id,
            'name' => 'Buyer',
            'phone' => '0700111222',
            'agreed_sale_price' => '100000.00',
            'deposit' => '10000.00',
            'status' => ClientStatus::Active,
        ]);

        $payload = [
            'client_id' => $client->id,
            'sale_building_id' => $building->id,
            'amount' => '5000.00',
            'discount' => '0.00',
            'paid_at' => '2026-07-01',
        ];

        $first = $this->actingAs($user)->postJson('/api/v1/sales/payments', $payload)->assertCreated();
        $second = $this->actingAs($user)->postJson('/api/v1/sales/payments', $payload)->assertOk();

        $this->assertDatabaseCount('sales_payments', 1);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
    }
}
