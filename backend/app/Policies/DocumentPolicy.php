<?php

namespace App\Policies;

use App\Models\Document;
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

        if ($parent === null) {
            return false;
        }

        return $user->can('view', $parent);
    }
}
