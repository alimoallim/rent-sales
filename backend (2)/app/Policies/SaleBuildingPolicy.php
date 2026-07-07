<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SaleBuilding;
use App\Models\User;

class SaleBuildingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function view(User $user, SaleBuilding $saleBuilding): bool
    {
        return $user->canAccessSales();
    }

    public function create(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function update(User $user, SaleBuilding $saleBuilding): bool
    {
        return $user->canAccessSales();
    }

    public function delete(User $user, SaleBuilding $saleBuilding): bool
    {
        return $user->canAccessSales();
    }
}
