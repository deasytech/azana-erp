<?php

namespace App\Domain\Inventory\Concerns;

use App\Domain\System\Actions\AssertMayDecide;
use App\Enums\Module;
use App\Models\User;

/** Who may approve stock corrections: someone with inventory.approve, and (by default) not the person who raised it. */
trait ChecksInventoryApproval
{
    private function assertMayDecide(User $decider, ?int $raisedBy, string $what): void
    {
        app(AssertMayDecide::class)($decider, $raisedBy, Module::Inventory, $what);
    }
}
