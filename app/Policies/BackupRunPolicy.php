<?php

namespace App\Policies;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Models\User;

/** The backup history is a record: viewed, and added to by taking a backup or a restore test, never edited or deleted. */
class BackupRunPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Backups;
    }

    /** The record is never edited or deleted, by anyone. */
    protected function can(User $user, PermissionAction $action): bool
    {
        return ! in_array($action, [PermissionAction::Edit, PermissionAction::Delete], true) && parent::can($user, $action);
    }
}
