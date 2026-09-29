<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

class UserPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Users;
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
