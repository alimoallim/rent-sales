<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class RentalModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function view(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function create(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function update(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function delete(User $user): bool
    {
        return $user->canAccessRental();
    }
}
