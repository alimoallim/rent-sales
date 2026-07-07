<?php

namespace App\Policies;

use App\Models\SalesPayment;
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

    public function cancel(User $user, SalesPayment $payment): bool
    {
        return $user->canAccessSales() && ($user->isManager() || $user->isAdmin());
    }

    public function delete(User $user): bool
    {
        return $user->canAccessSales();
    }
}
