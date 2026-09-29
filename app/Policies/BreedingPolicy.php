<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Breeding and litter records are corrected by new records or approval, never deleted. */
class BreedingPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Breeding;
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
