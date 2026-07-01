<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RentalBuilding;
use App\Models\User;

class RentalBuildingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function view(User $user, RentalBuilding $rentalBuilding): bool
    {
        return $user->canAccessRental();
    }

    public function create(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function update(User $user, RentalBuilding $rentalBuilding): bool
    {
        return $user->canAccessRental();
    }

    public function delete(User $user, RentalBuilding $rentalBuilding): bool
    {
        return $user->canAccessRental();
    }
}
