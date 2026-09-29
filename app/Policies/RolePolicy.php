<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\Role;
use App\Models\User;

class RolePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Roles;
    }

    /** Roles assigned to users are part of the access history and cannot be deleted. */
    public function delete(User $user, mixed $model = null): bool
    {
        return $model instanceof Role
            && $model->users()->doesntExist()
            && parent::delete($user, $model);
    }
}
