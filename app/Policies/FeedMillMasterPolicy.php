<?php

namespace App\Policies;

use App\Enums\Module;

/** Formulas: a draft that no order uses may be deleted; anything else is history. */
class FeedMillMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::FeedMill;
    }
}
