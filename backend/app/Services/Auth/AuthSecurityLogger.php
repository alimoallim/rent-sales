<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthSecurityLogger
{
    public function loginFailed(Request $request, ?string $username = null): void
    {
        $this->log('auth.login.failed', $request, [
            'username' => $username ?? $request->input('username'),
        ]);
    }

    public function loginSucceeded(Request $request, int $userId): void
    {
        $this->log('auth.login.success', $request, [
            'user_id' => $userId,
        ]);
    }

    public function logout(Request $request, ?int $userId): void
    {
        $this->log('auth.logout', $request, [
            'user_id' => $userId,
        ]);
    }

    public function passwordChanged(Request $request, int $userId): void
    {
        $this->log('auth.password.changed', $request, [
            'user_id' => $userId,
        ]);
    }

    public function passwordResetRequested(Request $request, string $email): void
    {
        $this->log('auth.password.reset_requested', $request, [
            'email' => $email,
        ]);
    }

    public function passwordResetCompleted(Request $request, string $email): void
    {
        $this->log('auth.password.reset_completed', $request, [
            'email' => $email,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $event, Request $request, array $context = []): void
    {
        Log::info($event, array_merge($context, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));
    }
}
