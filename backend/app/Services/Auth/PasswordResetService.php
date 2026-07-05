<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public const CODE_LENGTH = 6;

    public const MAX_ATTEMPTS = 5;

    public function ttlMinutes(): int
    {
        return (int) config('auth.password_reset_code_ttl', 15);
    }

    /**
     * Send a reset code when a matching active user with email exists.
     * Always returns without leaking whether the account exists.
     */
    public function sendResetCode(string $email): void
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();

        if ($user === null || empty($user->email) || $user->status !== UserStatus::Active) {
            return;
        }

        PasswordResetCode::query()->where('user_id', $user->id)->delete();

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        PasswordResetCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ]);

        Mail::to($user->email)->send(new PasswordResetCodeMail($user, $code));
    }

    public function verifyResetCode(string $email, string $code): void
    {
        $user = $this->resolveResetUser($email);
        $record = $this->resolveActiveResetRecord($user);

        $this->assertCodeMatches($record, $code);
    }

    public function resetPassword(string $email, string $code, string $password): void
    {
        $user = $this->resolveResetUser($email);
        $record = $this->resolveActiveResetRecord($user);

        $this->assertCodeMatches($record, $code);

        $user->update(['password' => $password]);
        PasswordResetCode::query()->where('user_id', $user->id)->delete();
    }

    private function resolveResetUser(string $email): User
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();

        if ($user === null || empty($user->email) || $user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => ['We could not reset the password for this account.'],
            ]);
        }

        return $user;
    }

    private function resolveActiveResetRecord(User $user): PasswordResetCode
    {
        $record = PasswordResetCode::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'code' => ['This verification code has expired. Request a new one.'],
            ]);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->delete();

            throw ValidationException::withMessages([
                'code' => ['Too many invalid attempts. Request a new verification code.'],
            ]);
        }

        return $record;
    }

    private function assertCodeMatches(PasswordResetCode $record, string $code): void
    {
        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            throw ValidationException::withMessages([
                'code' => ['The verification code is incorrect.'],
            ]);
        }
    }
}
