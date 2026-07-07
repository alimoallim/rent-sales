<?php

namespace App\Http\Requests\Auth;

use App\Rules\UniqueActiveUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                UniqueActiveUser::column('email', $this->user()?->id),
            ],
        ];
    }
}
