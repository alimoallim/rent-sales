<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Rules\UniqueActiveUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'username' => ['sometimes', 'required', 'string', 'max:200', 'alpha_dash', UniqueActiveUser::column('username', $userId)],
            'email' => ['nullable', 'email', 'max:255', UniqueActiveUser::column('email', $userId)],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'role' => ['sometimes', 'required', new Enum(UserRole::class)],
            'status' => ['sometimes', new Enum(UserStatus::class)],
            'is_manager' => ['sometimes', 'boolean'],
        ];
    }
}
