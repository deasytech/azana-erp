<?php

namespace App\Policies;

use App\Models\User;

/** Master data can be deactivated freely but only deleted while nothing references it. */
abstract class MasterRecordPolicy extends ModulePolicy
{
    public function delete(User $user, mixed $model = null): bool
    {
        if (is_object($model) && method_exists($model, 'isInUse') && $model->isInUse()) {
            return false;
        }

        return parent::delete($user, $model);
    }
}
