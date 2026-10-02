<?php

namespace App\Policies;

use App\Enums\Module;

/** Items, stores, batches and suppliers: deletable only while no stock history refers to them. */
class InventoryMasterPolicy extends MasterRecordPolicy
{
    protected function module(): Module
    {
        return Module::Inventory;
    }
}
