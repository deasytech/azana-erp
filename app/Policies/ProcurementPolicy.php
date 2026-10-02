<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Requests, orders, receipts, invoices and payments are history: voided or cancelled, never deleted. */
class ProcurementPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Procurement;
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
