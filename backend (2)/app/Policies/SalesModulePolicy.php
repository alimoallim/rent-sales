<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class SalesModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function view(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function create(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function update(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function delete(User $user): bool
    {
        return $user->canAccessSales();
    }
}
