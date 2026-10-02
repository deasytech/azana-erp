<?php

namespace App\Domain\Inventory\Events;

use App\Domain\Inventory\Models\InventoryTransaction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event, dispatched only once the surrounding transaction has committed. */
class InventoryTransactionPosted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly InventoryTransaction $transaction) {}
}
