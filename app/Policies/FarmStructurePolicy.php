<?php

namespace App\Policies;

use App\Enums\Module;

class FarmStructurePolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::FarmStructure;
    }
}
