<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordWithCodeRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthSecurityLogger;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthSecurityLogger $securityLogger,
    ) {}

    public function login(LoginRequest $request): UserResource
    {
        $user = User::query()->where('username', $request->string('username'))->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            $this->securityLogger->loginFailed($request);

            throw ValidationException::withMessages([
                'username' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            $this->securityLogger->loginFailed($request, $user->username);

            throw ValidationException::withMessages([
                'username' => ['This account is inactive.'],
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->securityLogger->loginSucceeded($request, $user->id);

        return new UserResource($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->securityLogger->logout($request, $userId);

        return response()->json(['message' => 'Logged out.']);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'password' => $request->string('password'),
        ]);

        $this->securityLogger->passwordChanged($request, $user->id);

        return response()->json(['message' => 'Password updated.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request, PasswordResetService $passwordResetService): JsonResponse
    {
        $email = $request->string('email')->toString();
        $passwordResetService->sendResetCode($email);

        $this->securityLogger->passwordResetRequested($request, $email);

        return response()->json([
            'message' => 'If an account with that email exists, we sent a verification code.',
        ]);
    }

    public function verifyResetCode(VerifyResetCodeRequest $request, PasswordResetService $passwordResetService): JsonResponse
    {
        $passwordResetService->verifyResetCode(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

        return response()->json([
            'message' => 'Verification code accepted.',
        ]);
    }

    public function resetPassword(ResetPasswordWithCodeRequest $request, PasswordResetService $passwordResetService): JsonResponse
    {
        $email = $request->string('email')->toString();
        $passwordResetService->resetPassword(
            $email,
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        $this->securityLogger->passwordResetCompleted($request, $email);

        return response()->json([
            'message' => 'Password has been reset. You can sign in now.',
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return new UserResource($user->fresh());
    }
}
