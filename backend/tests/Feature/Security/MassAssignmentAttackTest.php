<?php

namespace Tests\Feature\Security;

use App\Enums\RentPaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class MassAssignmentAttackTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    public function test_profile_update_cannot_escalate_role_to_admin(): void
    {
        $user = User::factory()->rental()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'Still Rental',
                'role' => UserRole::Admin->value,
                'is_manager' => true,
                'status' => UserStatus::Inactive->value,
            ])
            ->assertOk();

        $user->refresh();

        $this->assertSame(UserRole::Rental, $user->role);
        $this->assertFalse($user->is_manager);
        $this->assertSame(UserStatus::Active, $user->status);
    }

    public function test_rental_user_cannot_self_promote_via_admin_user_update(): void
    {
        $user = User::factory()->rental()->create();

        $this->actingAs($user)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'role' => UserRole::Admin->value,
                'is_manager' => true,
            ])
            ->assertForbidden();

        $user->refresh();
        $this->assertSame(UserRole::Rental, $user->role);
        $this->assertFalse($user->is_manager);
    }

    public function test_payment_create_ignores_status_and_created_by_overrides(): void
    {
        $user = $this->rentalUser();
        $otherUser = User::factory()->rental()->create();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '10000.00');

        $response = $this->actingAs($user)->postJson('/api/v1/rental/payments', $this->paymentPayload($tenant, '1000.00', [
            'status' => RentPaymentStatus::Voided->value,
            'created_by' => $otherUser->id,
            'voided_at' => now()->toIso8601String(),
            'voided_by' => $otherUser->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', RentPaymentStatus::Active->value);

        $this->assertDatabaseHas('rent_payments', [
            'tenant_id' => $tenant->id,
            'status' => RentPaymentStatus::Active->value,
            'created_by' => $user->id,
            'voided_at' => null,
        ]);
    }

    public function test_payment_update_ignores_status_mass_assignment(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);
        $this->postRentCharge($tenant, '10000.00');
        $payment = $this->createPaymentRecord($tenant, $user, '1000.00');

        $this->actingAs($user)
            ->putJson("/api/v1/rental/payments/{$payment->id}", $this->paymentPayload($tenant, '1500.00', [
                'status' => RentPaymentStatus::Voided->value,
                'voided_at' => now()->toIso8601String(),
                'voided_by' => $user->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.status', RentPaymentStatus::Active->value)
            ->assertJsonPath('data.amount', '1500.00');

        $payment->refresh();
        $this->assertSame(RentPaymentStatus::Active, $payment->status);
        $this->assertNull($payment->voided_at);
    }

    public function test_tenant_update_ignores_status_mass_assignment(): void
    {
        $user = $this->rentalUser();
        $tenant = $this->activeTenantSetup($user);

        $this->actingAs($user)
            ->putJson("/api/v1/rental/tenants/{$tenant->id}", [
                'rental_building_id' => $tenant->rental_building_id,
                'rental_unit_id' => $tenant->rental_unit_id,
                'name' => 'Renamed Tenant',
                'phone' => $tenant->phone,
                'status' => 'inactive',
                'created_by' => 999,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $tenant->refresh();
        $this->assertSame('active', $tenant->status->value);
        $this->assertSame($user->id, $tenant->created_by);
    }

    public function test_unit_create_ignores_occupied_status_override(): void
    {
        $user = User::factory()->admin()->create();
        $building = $this->rentalBuilding();

        $this->actingAs($user)
            ->postJson('/api/v1/rental/units', [
                'rental_building_id' => $building->id,
                'house_number' => 'Z9',
                'floor' => '9',
                'description' => 'Injected occupied',
                'monthly_rent' => 75000,
                'status' => 'occupied',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'vacant');
    }
}
