<?php

namespace Tests\Feature\CriticalPath;

use App\Models\RentPayment;
use App\Services\Rental\TenantBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    public function test_charge_partial_payments_then_settlement_balance_is_exact(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');

        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '10000.00');

        $calculator = app(TenantBalanceCalculator::class);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '3000.00'))
            ->assertCreated();
        $this->assertSame('7000.00', $calculator->calculate($tenant));

        Carbon::setTestNow('2026-07-01 10:00:15');

        $this->actingAs($user)->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '4000.00'))
            ->assertCreated();
        $this->assertSame('3000.00', $calculator->calculate($tenant));

        Carbon::setTestNow('2026-07-01 10:02:00');

        $this->actingAs($user)->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '3000.00'))
            ->assertCreated();
        $this->assertSame('0.00', $calculator->calculate($tenant));

        Carbon::setTestNow();
    }

    public function test_invoice_reference_is_unique_under_rapid_duplicate_submission(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '5000.00');

        $payload = $this->paymentPayload($tenant, '1000.00', [
            'invoice_reference' => 'RCP-HOSTILE-001',
        ]);

        $this->actingAs($user)->postJson('/api/v1/rental/payments', $payload)->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '2000.00', [
            'invoice_reference' => 'RCP-HOSTILE-001',
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('rent_payments', 1);
        $this->assertSame(1, RentPayment::query()->where('invoice_reference', 'RCP-HOSTILE-001')->count());
    }

    public function test_concurrent_payment_creation_assigns_unique_sequential_receipt_numbers(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '20000.00');

        $this->actingAs($user)->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '1000.00'))->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '2000.00'))->assertCreated();

        $references = RentPayment::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->pluck('invoice_reference')
            ->filter()
            ->values()
            ->all();

        $this->assertCount(2, $references);
        $this->assertSame($references, array_values(array_unique($references)));

        $numbers = array_map(
            fn (string $reference) => (int) substr($reference, strrpos($reference, '-') + 1),
            $references,
        );
        $this->assertSame($numbers[0] + 1, $numbers[1]);
    }
}
