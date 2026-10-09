<?php

namespace App\Policies;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Models\User;

/** Uploading and checking a file needs "create"; committing it needs "approve". An import is a record of what was loaded: it is never edited or deleted. */
class DataImportPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::DataImports;
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

    public function commit(User $user, mixed $model = null): bool
    {
        return $this->can($user, PermissionAction::Approve);
    }
}
