<?php

namespace App\Policies;

use App\Enums\Module;

class PriceListPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::PriceLists;
    }
}
