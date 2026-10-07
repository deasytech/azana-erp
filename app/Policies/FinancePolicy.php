<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** The ledger and finance records are history: reversed, voided or deactivated, never deleted. */
class FinancePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Finance;
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
