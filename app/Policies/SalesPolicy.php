<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Orders, invoices and payments are history: cancelled or voided, never deleted. */
class SalesPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Sales;
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
