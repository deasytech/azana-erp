<?php

namespace App\Policies;

use App\Enums\Module;

/** Feed types: deletable only while no consumption refers to them. */
class ProductionMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Production;
    }
}
