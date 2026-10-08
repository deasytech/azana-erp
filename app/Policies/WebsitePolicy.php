<?php

namespace App\Policies;

use App\Enums\Module;
use App\Models\User;

/** Enquiries come from the public: staff follow them up, close them or mark them as spam, but never create or delete them. */
class WebsitePolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Website;
    }

    public function create(User $user): bool
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
