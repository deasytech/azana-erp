<?php

namespace App\Policies;

use App\Enums\Module;

class MasterDataPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::MasterData;
    }
}
