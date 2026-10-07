<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Tasks are cancelled or completed, never deleted. */
class TaskPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Tasks;
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
