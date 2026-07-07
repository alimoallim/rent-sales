<?php

namespace Tests\Feature\Rental;

use App\Enums\RentPaymentStatus;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Services\Rental\TenantBalanceBatchService;
use App\Services\Rental\TenantBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class TenantBalanceBatchTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    #[Test]
    public function test_batch_balances_match_individual_calculator(): void
    {
        $user = $this->rentalUser();
        $tenantA = $this->activeTenantSetup($user);
        $tenantB = $this->activeTenantSetup($user, ['name' => 'Tenant B', 'phone' => '0700111222']);

        $this->postRentCharge($tenantA, '10000.00');
        $this->postRentCharge($tenantB, '25000.00');
        $this->createPaymentRecord($tenantA, $user, '3000.00');

        $batch = app(TenantBalanceBatchService::class);
        $calculator = app(TenantBalanceCalculator::class);

        $balances = $batch->totalDueForTenants([$tenantA->id, $tenantB->id]);

        $this->assertSame($calculator->calculate($tenantA), $balances[$tenantA->id]);
        $this->assertSame($calculator->calculate($tenantB), $balances[$tenantB->id]);
    }

    #[Test]
    public function test_batch_summary_matches_tenant_index(): void
    {
        $user = $this->rentalUser();
        $tenantA = $this->activeTenantSetup($user);
        $tenantB = $this->activeTenantSetup($user, ['name' => 'Tenant B', 'phone' => '0700111222']);
        $tenantC = $this->activeTenantSetup($user, ['name' => 'Tenant C', 'phone' => '0700333444']);

        $this->postRentCharge($tenantA, '5000.00');
        $this->postRentCharge($tenantB, '12000.00');
        $this->createPaymentRecord($tenantA, $user, '5000.00');

        RentPayment::createActive([
            'tenant_id' => $tenantC->id,
            'rental_building_id' => $tenantC->rental_building_id,
            'amount' => '1000.00',
            'discount' => '0.00',
            'paid_at' => '2026-07-01',
        ], $user->id);

        $response = $this->actingAs($user)->getJson('/api/v1/rental/tenants?status=active')
            ->assertOk();

        $response->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.with_balance', 1)
            ->assertJsonPath('summary.total_outstanding', '12000.00');
    }

    #[Test]
    public function test_with_balance_filter_uses_batch_balances(): void
    {
        $user = $this->rentalUser();
        $owingTenant = $this->activeTenantSetup($user);
        $paidTenant = $this->activeTenantSetup($user, ['name' => 'Paid Tenant', 'phone' => '0700555666']);

        $this->postRentCharge($owingTenant, '8000.00');
        $this->postRentCharge($paidTenant, '4000.00');
        $this->createPaymentRecord($paidTenant, $user, '4000.00');

        $response = $this->actingAs($user)->getJson('/api/v1/rental/tenants?status=active&with_balance=1')
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owingTenant->id)
            ->assertJsonPath('data.0.balance', '8000.00')
            ->assertJsonPath('summary.with_balance', 1);
    }

    #[Test]
    public function test_batch_breakdowns_match_individual_breakdown(): void
    {
        $user = $this->rentalUser();
        $tenantA = $this->activeTenantSetup($user);
        $tenantB = $this->activeTenantSetup($user, ['name' => 'Tenant B', 'phone' => '0700111222']);

        $this->postRentCharge($tenantA, '10000.00');
        $this->postRentCharge($tenantB, '25000.00');
        $this->createPaymentRecord($tenantA, $user, '3000.00');

        $breakdownService = app(\App\Services\Rental\TenantBalanceBreakdownService::class);
        $batch = $breakdownService->breakdownsForTenants([$tenantA->id, $tenantB->id]);

        $this->assertSame($breakdownService->breakdown($tenantA), $batch[$tenantA->id]);
        $this->assertSame($breakdownService->breakdown($tenantB), $batch[$tenantB->id]);
    }

    #[Test]
    public function test_tenants_without_charges_receive_zero_balance(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);

        $balances = app(TenantBalanceBatchService::class)->totalDueForTenants([$tenant->id]);

        $this->assertSame('0.00', $balances[$tenant->id]);
    }
}
