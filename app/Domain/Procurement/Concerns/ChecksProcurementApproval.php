<?php

namespace App\Domain\Procurement\Concerns;

use App\Domain\System\Actions\AssertMayDecide;
use App\Enums\Module;
use App\Models\User;

/** Who may approve purchase requests, orders and large payments: procurement.approve, and (by default) not the requester. */
trait ChecksProcurementApproval
{
    private function assertMayDecide(User $decider, ?int $raisedBy, string $what): void
    {
        app(AssertMayDecide::class)($decider, $raisedBy, Module::Procurement, $what);
    }
}
