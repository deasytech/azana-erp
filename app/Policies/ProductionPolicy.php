<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Batches, head-count events, weigh-ins, feed records and costs are history: voided or closed, never deleted. */
class ProductionPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Production;
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
