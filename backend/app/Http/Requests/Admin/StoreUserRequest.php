<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Rules\UniqueActiveUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:200', 'alpha_dash', UniqueActiveUser::column('username')],
            'email' => ['nullable', 'email', 'max:255', UniqueActiveUser::column('email')],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'role' => ['required', new Enum(UserRole::class)],
            'status' => ['sometimes', new Enum(UserStatus::class)],
            'is_manager' => ['sometimes', 'boolean'],
        ];
    }
}
