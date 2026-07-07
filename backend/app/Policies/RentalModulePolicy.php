<?php

namespace App\Policies;

use App\Models\RentPayment;
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

    public function void(User $user, RentPayment $payment): bool
    {
        return $user->canAccessRental() && ($user->isManager() || $user->isAdmin());
    }

    public function delete(User $user): bool
    {
        return $user->canAccessRental();
    }
}
