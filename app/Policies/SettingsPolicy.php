<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Settings rows are created from the registered definitions, never by hand, and never deleted. */
class SettingsPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Settings;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }
}
