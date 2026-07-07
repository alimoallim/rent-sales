<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->rental()->count(2)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_rental_user_cannot_manage_users(): void
    {
        $user = User::factory()->rental()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'New Rental User',
                'username' => 'newrental',
                'password' => 'Str0ng!Pass',
                'password_confirmation' => 'Str0ng!Pass',
                'role' => UserRole::Rental->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.username', 'newrental')
            ->assertJsonPath('data.role', UserRole::Rental->value)
            ->assertJsonPath('data.can_access_rental', true)
            ->assertJsonPath('data.can_access_sales', false);

        $this->assertDatabaseHas('users', [
            'username' => 'newrental',
            'role' => UserRole::Rental->value,
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/users/{$admin->id}")
            ->assertForbidden();
    }

    public function test_admin_can_access_both_modules(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/rental/dashboard')
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/v1/sales/dashboard')
            ->assertOk();
    }

    public function test_me_endpoint_includes_permission_flags(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.can_access_rental', true)
            ->assertJsonPath('data.can_access_sales', true);
    }

    public function test_admin_can_update_user_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->sales()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'password' => 'N3w!Passw0rd',
                'password_confirmation' => 'N3w!Passw0rd',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('N3w!Passw0rd', $user->fresh()->password));
    }

    public function test_admin_can_reuse_username_after_soft_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $retired = User::factory()->rental()->create([
            'username' => 'retired_staff',
            'email' => 'retired@example.com',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/users/{$retired->id}")
            ->assertOk();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Replacement Staff',
                'username' => 'retired_staff',
                'email' => 'new.hire@example.com',
                'password' => 'Str0ng!Pass',
                'password_confirmation' => 'Str0ng!Pass',
                'role' => UserRole::Rental->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.username', 'retired_staff');

        $this->assertSame(1, User::query()->where('username', 'retired_staff')->count());
        $this->assertSame(2, User::withTrashed()->where('username', 'retired_staff')->count());
    }

    public function test_admin_cannot_create_duplicate_active_username(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->rental()->create(['username' => 'taken.user']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Duplicate User',
                'username' => 'taken.user',
                'password' => 'Str0ng!Pass',
                'password_confirmation' => 'Str0ng!Pass',
                'role' => UserRole::Rental->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }
}
