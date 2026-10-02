<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** The stock ledger, counts and adjustments are history: reversed or rejected, never deleted. */
class InventoryPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Inventory;
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
