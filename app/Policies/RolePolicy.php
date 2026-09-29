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

    /** Only an Owner may change the Owner role (e.g. switch off its 2FA requirement). */
    public function update(User $user, mixed $model = null): bool
    {
        if ($model instanceof Role && $model->name === Role::OWNER && ! $user->isOwner()) {
            return false;
        }

        return parent::update($user, $model);
    }

    /** Roles assigned to users are part of the access history and cannot be deleted. */
    public function delete(User $user, mixed $model = null): bool
    {
        return $model instanceof Role
            && $model->name !== Role::OWNER
            && $model->users()->doesntExist()
            && parent::delete($user, $model);
    }
}
