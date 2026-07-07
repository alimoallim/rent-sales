<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'auth me' => ['GET', '/api/v1/auth/me'],
            'auth logout' => ['POST', '/api/v1/auth/logout'],
            'auth profile' => ['PATCH', '/api/v1/auth/profile'],
            'auth password' => ['PUT', '/api/v1/auth/password'],
            'rental dashboard' => ['GET', '/api/v1/rental/dashboard'],
            'rental buildings index' => ['GET', '/api/v1/rental/buildings'],
            'rental buildings store' => ['POST', '/api/v1/rental/buildings'],
            'rental units index' => ['GET', '/api/v1/rental/units'],
            'rental tenants index' => ['GET', '/api/v1/rental/tenants'],
            'rental tenants store' => ['POST', '/api/v1/rental/tenants'],
            'rental payments index' => ['GET', '/api/v1/rental/payments'],
            'rental payments store' => ['POST', '/api/v1/rental/payments'],
            'rental charge batch generate' => ['POST', '/api/v1/rental/charge-batches/generate'],
            'rental reports tenant balances' => ['GET', '/api/v1/rental/reports/tenant-balances'],
            'rental expenses index' => ['GET', '/api/v1/rental/expenses'],
            'sales dashboard' => ['GET', '/api/v1/sales/dashboard'],
            'sales clients index' => ['GET', '/api/v1/sales/clients'],
            'sales payments store' => ['POST', '/api/v1/sales/payments'],
            'sales reports balance' => ['GET', '/api/v1/sales/reports/balance'],
            'admin users index' => ['GET', '/api/v1/admin/users'],
            'admin users store' => ['POST', '/api/v1/admin/users'],
            'admin activity log' => ['GET', '/api/v1/admin/activity-log'],
            'admin settings' => ['GET', '/api/v1/admin/settings'],
            'admin recycle bin' => ['GET', '/api/v1/admin/recycle-bin'],
            'documents show' => ['GET', '/api/v1/documents/1'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_guest_cannot_access_protected_endpoint(string $method, string $uri): void
    {
        $payload = str_contains($uri, 'store') || str_contains($uri, 'generate') || str_contains($uri, 'payments')
            ? ['name' => 'probe']
            : (str_contains($uri, 'profile') ? ['name' => 'Hacker'] : []);

        $response = $this->json($method, $uri, $payload);

        $response->assertUnauthorized();
    }
}
