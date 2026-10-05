<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Production orders and finished batches are history: cancelled or reversed, never deleted. */
class FeedMillPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::FeedMill;
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
