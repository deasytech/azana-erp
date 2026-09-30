<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Visitor and inspection records are retained permanently. */
class BiosecurityPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Biosecurity;
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
