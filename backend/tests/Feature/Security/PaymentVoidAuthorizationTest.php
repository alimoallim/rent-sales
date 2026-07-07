<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class PaymentVoidAuthorizationTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    public function test_non_manager_cannot_void_rental_payment(): void
    {
        $rentalUser = $this->rentalUser();
        $tenant = $this->activeTenantSetup($rentalUser);
        $payment = $this->createPaymentRecord($tenant, $rentalUser, '1000.00');

        $this->actingAs($rentalUser)
            ->postJson("/api/v1/rental/payments/{$payment->id}/void")
            ->assertForbidden();
    }

    public function test_manager_can_void_rental_payment(): void
    {
        $manager = User::factory()->rental()->manager()->create();
        $tenant = $this->activeTenantSetup($manager);
        $payment = $this->createPaymentRecord($tenant, $manager, '1000.00');

        $this->actingAs($manager)
            ->postJson("/api/v1/rental/payments/{$payment->id}/void")
            ->assertOk();
    }
}
