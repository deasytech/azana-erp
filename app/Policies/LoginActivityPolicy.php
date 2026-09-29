<?php

namespace App\Policies;

use App\Enums\Module;

class LoginActivityPolicy extends AuditLogPolicy
{
    protected function module(): Module
    {
        return Module::LoginActivity;
    }
}
