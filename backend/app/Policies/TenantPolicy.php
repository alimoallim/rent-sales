<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->canAccessRental();
    }

    public function create(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->canAccessRental();
    }

    public function moveOut(User $user, Tenant $tenant): bool
    {
        return $user->canAccessRental();
    }
}
