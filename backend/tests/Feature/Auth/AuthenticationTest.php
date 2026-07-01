<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username_and_password(): void
    {
        $user = User::factory()->rental()->create([
            'username' => 'gelle',
            'password' => Hash::make('secret'),
        ]);

        $this->startSession();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'gelle',
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', UserRole::Rental->value)
            ->assertJsonPath('data.username', 'gelle');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->rental()->inactive()->create([
            'username' => 'inactive',
            'password' => Hash::make('secret'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'inactive',
            'password' => 'secret',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_rental_user_cannot_access_sales_routes(): void
    {
        $user = User::factory()->rental()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/sales/dashboard')
            ->assertForbidden();
    }

    public function test_sales_user_cannot_access_rental_routes(): void
    {
        $user = User::factory()->sales()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/rental/dashboard')
            ->assertForbidden();
    }

    public function test_admin_user_can_access_rental_and_sales_routes(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/rental/dashboard')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/sales/dashboard')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->sales()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->rental()->create([
            'password' => Hash::make('old-secret'),
        ]);

        $this->actingAs($user)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'old-secret',
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
    }
}
