<?php

namespace App\Policies;

use App\Enums\Module;

/** Customers: deletable only while they have no sales history. */
class SalesMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Sales;
    }
}
