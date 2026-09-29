<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Animals are never deleted: they leave the farm through a status change. */
class AnimalPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Animals;
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
