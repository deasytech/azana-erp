<?php

namespace App\Policies;

use App\Enums\Module;

class BiosecurityMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Biosecurity;
    }
}
