<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Slaughter days, records, carcasses and meat batches are history: cancelled, condemned or reversed, never deleted. */
class SlaughterPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Slaughter;
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
