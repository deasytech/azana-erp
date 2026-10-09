<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** The backup history is a record: viewed, and added to by taking a backup or a restore test, never edited or deleted. */
class BackupRunPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Backups;
    }

    public function update(User $user, mixed $model = null): bool
    {
        return false;
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
