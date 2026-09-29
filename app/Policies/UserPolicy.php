<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\Role;
use App\Models\User;

class UserPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Users;
    }

    /** Non-owners may not edit Owner accounts (password reset, reactivation or role changes would be a takeover). */
    public function update(User $user, mixed $model = null): bool
    {
        if ($model instanceof User && $model->hasRole(Role::OWNER) && ! $user->isOwner()) {
            return false;
        }

        return parent::update($user, $model);
    }

    /** Accounts carry audit history: deactivate instead of deleting. */
    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
