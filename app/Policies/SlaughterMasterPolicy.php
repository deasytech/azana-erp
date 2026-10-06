<?php

namespace App\Policies;

use App\Enums\Module;

/** The product catalogue: deletable only while no meat has been made from a product. */
class SlaughterMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Slaughter;
    }
}
