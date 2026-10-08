<?php

namespace App\Policies;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Models\User;

/** Enquiries come from the public: staff follow them up, close them or mark them as spam, but never create or delete them. */
class WebsitePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Website;
    }

    protected function can(User $user, PermissionAction $action): bool
    {
        return ! in_array($action, [PermissionAction::Create, PermissionAction::Delete], true) && parent::can($user, $action);
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, PermissionAction::Delete);
    }
}
