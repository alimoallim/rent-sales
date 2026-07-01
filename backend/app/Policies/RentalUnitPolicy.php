<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RentalUnit;
use App\Models\User;

class RentalUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function view(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->canAccessRental();
    }

    public function create(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function update(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->canAccessRental();
    }

    public function delete(User $user, RentalUnit $rentalUnit): bool
    {
        return $user->canAccessRental();
    }
}
