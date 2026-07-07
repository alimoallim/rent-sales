<?php

namespace Tests\Feature\CriticalPath;

use App\Services\Rental\TenantBalanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class BusinessRulesTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    public function test_overlapping_lease_on_occupied_unit_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $unit = $tenant->unit;

        $this->actingAs($user)
            ->postJson('/api/v1/rental/tenants', [
                'rental_building_id' => $tenant->rental_building_id,
                'rental_unit_id' => $unit->id,
                'name' => 'Squatter',
                'phone' => '0700444333',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rental_unit_id']);
    }

    public function test_overpayment_without_acknowledgement_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '5000.00');

        $this->actingAs($user)
            ->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '6000.00'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['overpayment_acknowledged']);

        $this->assertDatabaseCount('rent_payments', 0);
    }

    public function test_double_submitting_same_payment_records_exactly_one_payment(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '10000.00');

        $payload = $this->paymentPayload($tenant, '2500.00');

        $first = $this->actingAs($user)->postJson('/api/v1/rental/payments', $payload)->assertCreated();
        $second = $this->actingAs($user)->postJson('/api/v1/rental/payments', $payload)->assertOk();

        $this->assertDatabaseCount('rent_payments', 1);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('7500.00', app(TenantBalanceCalculator::class)->calculate($tenant));
    }

    public function test_charge_batch_generation_twice_for_same_period_creates_no_duplicate_charges(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);

        $first = $this->actingAs($user)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $tenant->rental_building_id,
            'billing_month' => 8,
            'billing_year' => 2026,
        ])->assertOk();

        $batchId = (int) $first->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/rental/charge-batches/{$batchId}/approve-all")->assertOk();

        $this->actingAs($user)->postJson('/api/v1/rental/charge-batches/generate', [
            'building_id' => $tenant->rental_building_id,
            'billing_month' => 8,
            'billing_year' => 2026,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('rent_charges', 1);
    }

    public function test_cannot_delete_occupied_unit_with_active_lease(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->createPaymentRecord($tenant, $user, '1000.00');

        $this->actingAs($user)
            ->deleteJson("/api/v1/rental/units/{$tenant->rental_unit_id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('rental_units', [
            'id' => $tenant->rental_unit_id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_delete_building_that_still_has_units(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->createPaymentRecord($tenant, $user, '1000.00');

        $this->actingAs($user)
            ->deleteJson("/api/v1/rental/buildings/{$tenant->rental_building_id}")
            ->assertUnprocessable();
    }

    public function test_payment_amount_zero_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);

        $this->actingAs($user)
            ->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '0.00'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_payment_amount_negative_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);

        $this->actingAs($user)
            ->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '-100.00'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_payment_amount_with_three_decimal_places_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);

        $this->actingAs($user)
            ->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '100.123'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_payment_amount_string_is_rejected(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);

        $this->actingAs($user)
            ->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, 'not-money'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_payment_huge_amount_is_accepted_when_acknowledged(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '100.00');

        $this->actingAs($user)
            ->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '999999999.99', [
                'overpayment_acknowledged' => true,
            ]))
            ->assertCreated();
    }
}
