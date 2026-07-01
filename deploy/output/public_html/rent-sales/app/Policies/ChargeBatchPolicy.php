<?php

namespace App\Policies;

use App\Models\ChargeBatch;
use App\Models\User;

class ChargeBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function view(User $user, ChargeBatch $chargeBatch): bool
    {
        return $user->canAccessRental();
    }

    public function generate(User $user): bool
    {
        return $user->canAccessRental();
    }

    public function update(User $user, ChargeBatch $chargeBatch): bool
    {
        return $user->canAccessRental() && $chargeBatch->isEditable();
    }

    public function approve(User $user, ChargeBatch $chargeBatch): bool
    {
        return $user->canAccessRental() && $chargeBatch->isEditable();
    }
}
