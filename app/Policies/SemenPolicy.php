<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Collections, batches and QC results are history: quarantined, destroyed or expired, never deleted. */
class SemenPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Semen;
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
