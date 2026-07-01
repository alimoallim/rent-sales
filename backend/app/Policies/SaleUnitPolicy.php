<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SaleUnit;
use App\Models\User;

class SaleUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function view(User $user, SaleUnit $saleUnit): bool
    {
        return $user->canAccessSales();
    }

    public function create(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function update(User $user, SaleUnit $saleUnit): bool
    {
        return $user->canAccessSales();
    }

    public function delete(User $user, SaleUnit $saleUnit): bool
    {
        return $user->canAccessSales();
    }
}
