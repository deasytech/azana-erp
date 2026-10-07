<?php

namespace App\Policies;

use App\Enums\Module;

/** Management targets (and what the dashboards show) follow the reports module's permissions. */
class ReportsPolicy extends ModulePolicy
{
    protected function module(): Module
    {
        return Module::Reports;
    }
}
