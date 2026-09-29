<?php

namespace App\Policies;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Models\User;

/** Maps Filament/Laravel policy methods onto the "{module}.{action}" permission matrix. */
abstract class ModulePolicy
{
    abstract protected function module(): Module;

    protected function can(User $user, PermissionAction $action): bool
    {
        return $user->is_active && $user->can($this->module()->permission($action));
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, PermissionAction::View);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->can($user, PermissionAction::View);
    }

    public function create(User $user): bool
    {
        return $this->can($user, PermissionAction::Create);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->can($user, PermissionAction::Edit);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->can($user, PermissionAction::Delete);
    }

    public function approve(User $user, mixed $model = null): bool
    {
        return $this->can($user, PermissionAction::Approve);
    }

    public function export(User $user): bool
    {
        return $this->can($user, PermissionAction::Export);
    }

    public function print(User $user, mixed $model = null): bool
    {
        return $this->can($user, PermissionAction::Print);
    }
}
