<?php

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Models\User;

/** Photos are not history: whoever may edit animals may remove a wrong photo. */
class AnimalPhotoPolicy extends AnimalPolicy
{
    public function delete(User $user, mixed $model = null): bool
    {
        return $this->can($user, PermissionAction::Edit);
    }
}
