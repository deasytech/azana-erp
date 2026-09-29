<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Read-only, always. */
class AuditLogPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::AuditLogs;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }
}
