<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_code_for_active_user_with_email(): void
    {
        Mail::fake();

        $user = User::factory()->rental()->create([
            'email' => 'staff@example.com',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'staff@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'If an account with that email exists, we sent a verification code.');

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertDatabaseHas('password_reset_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'If an account with that email exists, we sent a verification code.');

        Mail::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_code(): void
    {
        $user = User::factory()->rental()->create([
            'email' => 'staff@example.com',
            'password' => Hash::make('old-secret'),
        ]);

        $code = '123456';

        PasswordResetCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'staff@example.com',
            'code' => $code,
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ])->assertOk();

        $this->assertTrue(Hash::check('Str0ng!Pass', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_reset_password_rejects_weak_password(): void
    {
        $user = User::factory()->rental()->create([
            'email' => 'staff@example.com',
        ]);

        PasswordResetCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'staff@example.com',
            'code' => '123456',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_rejects_invalid_code(): void
    {
        $user = User::factory()->rental()->create([
            'email' => 'staff@example.com',
        ]);

        PasswordResetCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'staff@example.com',
            'code' => '000000',
            'password' => 'Str0ng!Pass',
            'password_confirmation' => 'Str0ng!Pass',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_user_can_verify_reset_code_before_changing_password(): void
    {
        $user = User::factory()->rental()->create([
            'email' => 'staff@example.com',
        ]);

        $code = '654321';

        PasswordResetCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'email' => 'staff@example.com',
            'code' => $code,
        ])->assertOk();

        $this->assertDatabaseHas('password_reset_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_verify_reset_code_rejects_invalid_code(): void
    {
        $user = User::factory()->rental()->create([
            'email' => 'staff@example.com',
        ]);

        PasswordResetCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'email' => 'staff@example.com',
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->rental()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_admin_can_view_system_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'app_name',
                    'frontend_url',
                    'mail' => ['driver', 'from_address', 'is_configured'],
                    'password_reset' => ['code_ttl_minutes'],
                ],
            ]);
    }

    public function test_non_admin_cannot_view_system_settings(): void
    {
        $user = User::factory()->sales()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/settings')
            ->assertForbidden();
    }
}
