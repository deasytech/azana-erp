<?php

namespace App\Policies;

use App\Models\User;

/**
 * The sync log is read, and its problems marked as reviewed, by supervisors (mobile.edit); nobody creates, edits or deletes its rows by hand.
 * Laravel passes the user and the record to every method; the ones that need neither simply do not declare them.
 */
class SyncMutationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('mobile.edit');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }
}
