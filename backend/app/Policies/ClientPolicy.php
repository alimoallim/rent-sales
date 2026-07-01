<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function view(User $user, Client $client): bool
    {
        return $user->canAccessSales();
    }

    public function create(User $user): bool
    {
        return $user->canAccessSales();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->canAccessSales();
    }

    public function disable(User $user, Client $client): bool
    {
        return $user->canAccessSales();
    }
}
