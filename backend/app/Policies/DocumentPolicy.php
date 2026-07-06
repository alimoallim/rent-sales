<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $this->canAccessParent($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->canAccessParent($user, $document);
    }

    private function canAccessParent(User $user, Document $document): bool
    {
        $parent = $document->documentable;

        if ($parent instanceof Tenant) {
            return $user->canAccessRental();
        }

        if ($parent instanceof Client) {
            return $user->canAccessSales();
        }

        return false;
    }
}
