<?php

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\Invoice;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A sale has been dispatched and invoiced. Dispatched only once the surrounding transaction has committed. */
class SaleConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
