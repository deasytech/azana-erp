<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Health records are permanent: corrected by new records or approval, never deleted. */
class HealthPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Health;
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
