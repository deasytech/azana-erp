<?php

namespace App\Policies;

use App\Models\User;

/** The sync log is read, and its problems marked as reviewed, by supervisors (mobile.edit); nobody creates, edits or deletes its rows by hand. */
class SyncMutationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('mobile.edit');
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
