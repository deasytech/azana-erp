<?php

namespace App\Policies;

use App\Enums\Module;

/** Suppliers: deletable only while no stock or document refers to them. */
class ProcurementMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Procurement;
    }
}
