<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'is_manager' => (bool) $this->is_manager,
            'is_admin' => $this->isAdmin(),
            'can_access_rental' => $this->canAccessRental(),
            'can_access_sales' => $this->canAccessSales(),
        ];
    }
}
