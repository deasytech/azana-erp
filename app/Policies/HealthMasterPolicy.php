<?php

namespace App\Policies;

use App\Enums\Module;

/** Diseases, medicines, batches and schedules: deletable only while nothing references them. */
class HealthMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Health;
    }
}
