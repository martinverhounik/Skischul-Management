<?php

namespace App\Policies;

use App\Models\User;

/** Account management is restricted to owners of the same school (US-31). */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner();
    }

    public function create(User $actor): bool
    {
        return $actor->isOwner();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isOwner() && $actor->school_id === $target->school_id;
    }

    /** Owners cannot lock themselves out. */
    public function deactivate(User $actor, User $target): bool
    {
        return $this->update($actor, $target) && $actor->isNot($target);
    }
}
