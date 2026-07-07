<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRentalDomain;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use InteractsWithRentalDomain;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: UserRole}>
     */
    public static function adminOnlyEndpoints(): array
    {
        return [
            'list users' => ['GET', '/api/v1/admin/users', UserRole::Rental],
            'create user' => ['POST', '/api/v1/admin/users', UserRole::Sales],
            'activity log' => ['GET', '/api/v1/admin/activity-log', UserRole::Rental],
            'settings' => ['GET', '/api/v1/admin/settings', UserRole::Sales],
            'recycle bin' => ['GET', '/api/v1/admin/recycle-bin', UserRole::Rental],
            'test email' => ['POST', '/api/v1/admin/settings/test-email', UserRole::Sales],
        ];
    }

    #[DataProvider('adminOnlyEndpoints')]
    public function test_low_privilege_user_cannot_access_admin_endpoints(
        string $method,
        string $uri,
        UserRole $role,
    ): void {
        $user = User::factory()->create(['role' => $role]);

        $payload = $method === 'POST' && str_contains($uri, 'users')
            ? [
                'name' => 'Escalated',
                'username' => 'escalated',
                'password' => 'Str0ng!Pass',
                'password_confirmation' => 'Str0ng!Pass',
                'role' => UserRole::Admin->value,
                'status' => UserStatus::Active->value,
            ]
            : (str_contains($uri, 'test-email') ? ['email' => 'probe@example.com'] : []);

        $this->actingAs($user)
            ->json($method, $uri, $payload)
            ->assertForbidden();
    }

    public function test_rental_user_cannot_mutate_sales_payments(): void
    {
        $user = User::factory()->rental()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/sales/payments', [
                'client_id' => 1,
                'sale_building_id' => 1,
                'amount' => '100.00',
                'paid_at' => '2026-06-01',
            ])
            ->assertForbidden();
    }

    public function test_sales_user_cannot_void_rental_payments(): void
    {
        $rentalUser = $this->rentalUser();
        $salesUser = User::factory()->sales()->create();
        $tenant = $this->activeTenantSetup($rentalUser);
        $payment = $this->createPaymentRecord($tenant, $rentalUser, '1000.00');

        $this->actingAs($salesUser)
            ->postJson("/api/v1/rental/payments/{$payment->id}/void")
            ->assertForbidden();
    }
}
